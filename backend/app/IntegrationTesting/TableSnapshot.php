<?php
declare(strict_types=1);

namespace App\IntegrationTesting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates per-worker shadow copies of all database tables.
 *
 * Each Playwright worker gets its own set of prefixed tables
 * (e.g., _pw1a2b3c_users) copied from the canonical seeded tables,
 * foreign keys included.
 * The middleware sets the DB connection prefix so all queries for
 * that worker hit the shadow tables, leaving originals untouched.
 *
 * Rollback = drop shadows + re-copy from originals.
 *
 * This rests on one assumption: application SQL is built by Eloquent or the
 * query builder, which apply the connection's table prefix. Raw SQL that
 * names a table literally bypasses the prefix and reaches the shared tables,
 * so workers would see each other's data. The same goes for a second
 * connection or database-backed sessions, cache or queues. See "Test
 * isolation" in docs/developers/testing.md.
 */
class TableSnapshot
{
    /**
     * Tables to skip when snapshotting.
     */
    protected const SKIP_TABLES = [
        'migrations',
    ];

    /**
     * Create shadow copies of all tables for a given token.
     */
    public static function capture(string $token): void
    {
        $prefix = static::prefix($token);
        $lockName = "snapshot_{$prefix}";

        // Use advisory lock to prevent concurrent snapshot creation
        $acquired = DB::selectOne('SELECT GET_LOCK(?, 10) as acquired', [$lockName]);
        if (! $acquired->acquired) {
            Log::channel('single')->debug("[TableSnapshot] Could not acquire lock for {$token}");

            return;
        }

        try {
            if (static::exists($token)) {
                return;
            }

            $tables = static::tables();
            $start = microtime(true);

            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                foreach ($tables as $table) {
                    $shadow = $prefix . $table;
                    DB::statement(static::shadowDefinition($table, $prefix));
                    DB::statement("INSERT INTO `{$shadow}` SELECT * FROM `{$table}`");
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }

            $ms = round((microtime(true) - $start) * 1000);
            $count = count($tables);
            Log::channel('single')->debug(
                "[TableSnapshot] Captured {$count} tables for {$token} ({$ms}ms)"
            );
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    /**
     * Build the CREATE TABLE statement for a shadow copy of a table.
     *
     * `CREATE TABLE ... LIKE` drops foreign keys, which would leave cascades
     * and constraint violations untested. Rewriting the original definition
     * keeps them, pointed at the shadow copies of the referenced tables.
     * Constraint names are unique per schema, so they are renamed too.
     */
    protected static function shadowDefinition(string $table, string $prefix): string
    {
        $row = (array)DB::selectOne("SHOW CREATE TABLE `{$table}`");
        $definition = (string)($row['Create Table'] ?? '');

        $definition = preg_replace(
            '/^CREATE TABLE `[^`]+`/',
            "CREATE TABLE `{$prefix}{$table}`",
            $definition,
            1
        );
        $definition = preg_replace_callback(
            '/CONSTRAINT `([^`]+)`/',
            fn(array $match) => 'CONSTRAINT `' . $prefix . substr(md5($table . '.' . $match[1]), 0, 16) . '`',
            (string)$definition
        );

        return (string)preg_replace(
            '/REFERENCES `([^`]+)`/',
            "REFERENCES `{$prefix}$1`",
            (string)$definition
        );
    }

    /**
     * Drop shadow tables for a given token.
     */
    public static function drop(string $token): void
    {
        $prefix = static::prefix($token);
        $tables = static::tables();
        $start = microtime(true);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tables as $table) {
                DB::statement("DROP TABLE IF EXISTS `{$prefix}{$table}`");
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $ms = round((microtime(true) - $start) * 1000);
        Log::channel('single')->debug("[TableSnapshot] Dropped tables for {$token} ({$ms}ms)");
    }

    /**
     * Check if a snapshot exists for a given token.
     */
    public static function exists(string $token): bool
    {
        $prefix = static::prefix($token);
        $database = config('database.connections.mysql.database');

        $result = DB::selectOne(
            "SELECT COUNT(*) as cnt FROM information_schema.tables
             WHERE table_schema = ? AND table_name LIKE ?",
            [$database, $prefix . '%']
        );

        return ($result->cnt ?? 0) > 0;
    }

    /**
     * Drop ALL shadow tables from any previous test run.
     * Called during globalSetup / migrate:fresh.
     */
    public static function cleanup(): void
    {
        $database = config('database.connections.mysql.database');
        $shadows = DB::select(
            "SELECT table_name FROM information_schema.tables
             WHERE table_schema = ? AND table_name LIKE '\\_pw%'",
            [$database]
        );

        if (empty($shadows)) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($shadows as $row) {
                $name = $row->table_name ?? $row->TABLE_NAME;
                DB::statement("DROP TABLE IF EXISTS `{$name}`");
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        Log::channel('single')->debug('[TableSnapshot] Cleaned up ' . count($shadows) . ' shadow tables');
    }

    /**
     * Get all canonical (non-shadow) application tables.
     *
     * @return array<string>
     */
    protected static function tables(): array
    {
        $database = config('database.connections.mysql.database');
        $rows = DB::select(
            "SELECT table_name FROM information_schema.tables
             WHERE table_schema = ? AND table_type = 'BASE TABLE'
             AND table_name NOT LIKE '\\_pw%'
             AND table_name NOT LIKE '\\_old\\_%'",
            [$database]
        );

        $tables = [];
        foreach ($rows as $row) {
            $name = $row->table_name ?? $row->TABLE_NAME;
            if (! in_array($name, self::SKIP_TABLES, true)) {
                $tables[] = $name;
            }
        }

        return $tables;
    }

    /**
     * Generate a short, deterministic prefix from a token.
     */
    public static function prefix(string $token): string
    {
        return '_pw' . substr(md5($token), 0, 6) . '_';
    }
}
