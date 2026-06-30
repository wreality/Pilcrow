<?php
declare(strict_types=1);

namespace App\Policies;

use App\Models\Publication;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SubmissionPolicy
{
    use HandlesAuthorization;

    /**
     * Check if a submission can be created.
     *
     * Role-agnostic: a submission may be created whenever the target
     * publication is accepting submissions. This is the one genuinely
     * non-scoped submission check — it depends on the publication's state, not
     * the user's role — so it stays a policy method (gated by `@canModel`).
     * Every scoped submission check is handled by `@scopedCan` straight through
     * {@see \App\Auth\ScopedAbilityResolver}.
     *
     * @param \App\Models\User $user
     * @param array $args
     * @return bool
     */
    public function create(User $user, $args)
    {
        $publication = Publication::where('id', $args['publication_id'])->firstOrFail();

        return $publication->is_accepting_submissions;
    }
}
