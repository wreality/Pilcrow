<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Publication;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for SubmissionPolicy, now reduced to its one genuinely non-scoped
 * check: `create` depends on the target publication's state, not the caller's
 * role. Every scoped submission ability is resolved through
 * {@see \App\Auth\ScopedAbilityResolver} by the `@scopedCan` directive and is
 * covered by {@see ScopedAbilityResolverTest}.
 */
class SubmissionPolicyTest extends TestCase
{
    use RefreshDatabase;

    // ---- create (role-agnostic: only the publication's accepting flag) ------

    public function testCreateAllowsAnyUserWhenPublicationAcceptingSubmissions(): void
    {
        $publication = Publication::factory()->create(['is_accepting_submissions' => true]);

        $this->assertTrue(
            User::factory()->create()->can('create', [Submission::class, ['publication_id' => $publication->id]])
        );
    }

    public function testCreateDeniedWhenPublicationNotAcceptingSubmissions(): void
    {
        $publication = Publication::factory()->create(['is_accepting_submissions' => false]);

        $this->assertFalse(
            User::factory()->create()->can('create', [Submission::class, ['publication_id' => $publication->id]])
        );
    }
}
