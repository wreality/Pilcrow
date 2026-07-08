<?php
declare(strict_types=1);

namespace App\GraphQL\Directives;

use App\Auth\Abilities\ScopedAbility;
use App\Auth\ScopedAbilityResolver;
use Closure;
use Illuminate\Contracts\Auth\Access\Gate;
use Nuwave\Lighthouse\Auth\CanFindDirective;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Authorize a field through the SCOPED (publication / submission) authorization
 * engine, naming the capability ONCE — by the very enum the resolver checks.
 *
 * Where Laravel's `@canFind` names a policy method that in turn forwards to
 * {@see ScopedAbilityResolver} (the capability spelled three times across two
 * "ability" vocabularies), this directive resolves straight through the engine:
 * `@scopedCan(ability: "SubmissionAbility::Review", find: "submission_id")` runs
 * `ScopedAbilityResolver::allows($user, SubmissionAbility::Review, $submission)`.
 *
 * The `ability` argument is a reference to the PHP {@see ScopedAbility} case
 * itself ("EnumClass::Case"), not a mirrored GraphQL enum — the directive is
 * server-only (never printed in the client schema), so a parallel enum would be
 * a second list to keep in sync for no client benefit. This matches the sibling
 * `@abilityFields` directive, which likewise references the enum class by name.
 *
 * It reuses {@see CanFindDirective}'s `find` / `model` / `findOrFail` model
 * loading (including its client-safe not-found behaviour) but swaps the Gate
 * check for a resolver call: the `ability` is checked against the loaded model
 * (a submission, publication, or comment) by {@see ScopedAbilityResolver}.
 */
class ScopedCanDirective extends CanFindDirective
{
    /**
     * @param \Illuminate\Contracts\Auth\Access\Gate $gate
     * @param \App\Auth\ScopedAbilityResolver $scoped
     */
    public function __construct(Gate $gate, private ScopedAbilityResolver $scoped)
    {
        parent::__construct($gate);
    }

    /**
     * @return string
     */
    public static function definition(): string
    {
        //phpcs:disable
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Authorize a field through the scoped (publication / submission / comment)
authorization engine, checking `ability` against the found model.
"""
directive @scopedCan(
  """
  The scoped ability to check against the found model, written as a PHP
  enum-case reference "EnumClass::Case" (e.g. "SubmissionAbility::Review",
  "CommentAbility::Delete"). A bare class name is namespaced under
  App\\Auth\\Abilities.
  """
  ability: String!

  """
  The name of the field argument holding the model's primary key. Dot notation
  reaches into nested inputs.
  """
  find: String!

  """
  Class name of the model to load. Only needed when it cannot be inferred from
  the field's return type.
  """
  model: String

  """
  Should the field fail when the model named by `find` is not found?
  """
  findOrFail: Boolean! = true

  """
  Message thrown when authorization is denied.
  """
  message: String! = "UNAUTHORIZED"
) repeatable on FIELD_DEFINITION
GRAPHQL;
        //phpcs:enable
    }

    /**
     * Wrap the resolver, denying the field unless the acting user is permitted
     * for every model named by `find`.
     *
     * @param \Nuwave\Lighthouse\Schema\Values\FieldValue $fieldValue
     * @return void
     */
    public function handleField(FieldValue $fieldValue): void
    {
        $ability = $this->resolveAbilityArg();
        $message = $this->directiveArgValue('message', 'UNAUTHORIZED');

        $fieldValue->wrapResolver(fn(callable $resolver): Closure => function (
            mixed $root,
            array $args,
            GraphQLContext $context,
            ResolveInfo $resolveInfo
        ) use (
            $resolver,
            $ability,
            $message
        ) {
            $user = $context->user();

            foreach ($this->modelsToCheck($root, $args, $context, $resolveInfo) as $model) {
                $permitted = $user !== null && $this->scoped->allows($user, $ability, $model);

                if (! $permitted) {
                    // A guest is denied before any role/ownership is consulted —
                    // surface Lighthouse's generic message (matching the framework
                    // gate) rather than leaking the field's specific deny reason.
                    throw new AuthorizationException(
                        $user === null ? AuthorizationException::MESSAGE : $message
                    );
                }
            }

            return $resolver($root, $args, $context, $resolveInfo);
        });
    }

    /**
     * The `ability` argument as a ScopedAbility case.
     *
     * @return \App\Auth\Abilities\ScopedAbility
     */
    private function resolveAbilityArg(): ScopedAbility
    {
        $value = (string)$this->directiveArgValue('ability');

        // A PHP enum-case reference, "EnumClass::Case" — the capability is named
        // by the same enum the resolver checks. A bare class is namespaced under
        // App\Auth\Abilities; a fully-qualified one is used as written.
        [$class, $case] = array_pad(explode('::', $value, 2), 2, null);
        $fqcn = $class !== null && str_contains($class, '\\')
            ? $class
            : 'App\\Auth\\Abilities\\' . $class;

        $ability = $case !== null && enum_exists($fqcn) && defined("{$fqcn}::{$case}")
            ? constant("{$fqcn}::{$case}")
            : null;

        if (! $ability instanceof ScopedAbility) {
            throw new DefinitionException(
                "`@scopedCan` ability `{$value}` must reference a "
                . ScopedAbility::class . ' case as "EnumClass::Case".'
            );
        }

        return $ability;
    }
}
