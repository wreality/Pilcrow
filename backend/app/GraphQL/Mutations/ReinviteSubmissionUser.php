<?php
declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Auth\Roles\ScopedRole;
use App\Exceptions\ClientException;
use App\Models\Submission;
use App\Models\SubmissionInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class ReinviteSubmissionUser
{
    /**
     * Resend an invitation, with an optional message, to a staged reviewer of
     * the submission.
     *
     * @param null  $_
     * @param array{submission_id: int|string, user_id: int|string, message?: string}  $args
     * @return \App\Models\Submission
     * @throws \App\Exceptions\ClientException
     */
    public function reinviteReviewer($_, array $args)
    {
        $submission = Submission::where('id', $args['submission_id'])->firstOrFail();
        $user = $this->findStagedUser($submission->reviewers(), $args['user_id']);
        $invite = SubmissionInvitation::create([
            'submission_id' => $submission->id,
            'role' => ScopedRole::Reviewer->toSlug(),
            'role_id' => ScopedRole::Reviewer->legacyId(),
            'email' => $user->email,
            'message' => $args['message'] ?? null,
        ]);

        return $invite->reinviteReviewer();
    }

    /**
     * Resend an invitation, with an optional message, to a staged review
     * coordinator of the submission.
     *
     * @param null  $_
     * @param array{submission_id: int|string, user_id: int|string, message?: string}  $args
     * @return \App\Models\Submission
     * @throws \App\Exceptions\ClientException
     */
    public function reinviteReviewCoordinator($_, array $args)
    {
        $submission = Submission::where('id', $args['submission_id'])->firstOrFail();
        $user = $this->findStagedUser($submission->reviewCoordinators(), $args['user_id']);
        $invite = SubmissionInvitation::create([
            'submission_id' => $submission->id,
            'role' => ScopedRole::ReviewCoordinator->toSlug(),
            'role_id' => ScopedRole::ReviewCoordinator->legacyId(),
            'email' => $user->email,
            'message' => $args['message'] ?? null,
        ]);

        return $invite->reinviteReviewCoordinator();
    }

    /**
     * Resolve the reinvite target from the submission's assignments for the
     * role, so a reinvite can only go to a staged user already on the team. The
     * email is read here, server-side, so callers never need to see it.
     *
     * @param \Illuminate\Database\Eloquent\Relations\BelongsToMany $assigned
     * @param string|int $userId
     * @return \App\Models\User
     * @throws \App\Exceptions\ClientException
     */
    private function findStagedUser(BelongsToMany $assigned, int|string $userId): User
    {
        /** @var \App\Models\User|null $user */
        $user = $assigned
            ->where('users.id', $userId)
            ->where('users.staged', true)
            ->first();

        if (!$user) {
            throw new ClientException(
                'The user is not a pending invitee of this submission in this role.',
                'submissionInvitation',
                'REINVITE_TARGET_NOT_STAGED'
            );
        }

        return $user;
    }
}
