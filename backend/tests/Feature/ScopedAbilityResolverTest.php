<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\Abilities\CommentAbility;
use App\Auth\Abilities\SubmissionAbility;
use App\Auth\Roles\GlobalRole;
use App\Auth\Roles\ScopedRole;
use App\Auth\ScopedAbilityResolver;
use App\Models\InlineComment;
use App\Models\OverallComment;
use App\Models\Publication;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Behavioral coverage for scoped (submission) authorization, resolved straight
 * through {@see ScopedAbilityResolver} — the engine the `@scopedCan` directive
 * calls in place of the former policy shims. These lock in the corrected
 * submission ability matrix (e.g. submitters edit content / status only while
 * DRAFT, reviewers comment only while reviewable, invite is review-coordinator
 * and up).
 *
 * Comment edit/delete is a comment-scoped {@see CommentAbility} resolved against
 * the comment through its owning submission — the author may revise or retract
 * their own comment while review is open, and an app administrator moderates any.
 */
class ScopedAbilityResolverTest extends TestCase
{
    use RefreshDatabase;

    private ScopedAbilityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ScopedAbilityResolver();
    }

    private function appAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(GlobalRole::ApplicationAdministrator);

        return $admin;
    }

    private function attachToPublication(User $user, Publication $publication, string $roleId): void
    {
        $user->publications()->attach($publication->id, ['role' => $roleId]);
    }

    private function attachToSubmission(User $user, Submission $submission, string $roleId): void
    {
        $submission->users()->attach($user->id, ['role' => $roleId]);
    }

    /**
     * Submission with its own publication, default status DRAFT.
     */
    private function makeSubmission(int $status = Submission::DRAFT): Submission
    {
        $publication = Publication::factory()->create();

        return Submission::factory()->for($publication)->create(['status' => $status]);
    }

    private function allows(User $user, SubmissionAbility $ability, Submission $submission): bool
    {
        return $this->resolver->allows($user, $ability, $submission);
    }

    // ---- updateSubmitters ---------------------------------------------------

    public function testUpdateSubmittersAllowsApplicationAdministrator(): void
    {
        $this->assertTrue($this->allows($this->appAdmin(), SubmissionAbility::UpdateSubmitters, $this->makeSubmission()));
    }

    public function testUpdateSubmittersAllowsPublicationAdminAndEditor(): void
    {
        foreach ([ScopedRole::PublicationAdmin->toSlug(), ScopedRole::Editor->toSlug()] as $roleId) {
            $submission = $this->makeSubmission();
            $user = User::factory()->create();
            $this->attachToPublication($user, $submission->publication, $roleId);

            $this->assertTrue($this->allows($user, SubmissionAbility::UpdateSubmitters, $submission), "role_id $roleId");
        }
    }

    public function testUpdateSubmittersAllowsSubmitterAndReviewCoordinator(): void
    {
        foreach ([ScopedRole::Submitter->toSlug(), ScopedRole::ReviewCoordinator->toSlug()] as $roleId) {
            $submission = $this->makeSubmission();
            $user = User::factory()->create();
            $this->attachToSubmission($user, $submission, $roleId);

            $this->assertTrue($this->allows($user, SubmissionAbility::UpdateSubmitters, $submission), "role_id $roleId");
        }
    }

    public function testUpdateSubmittersDeniesReviewer(): void
    {
        $submission = $this->makeSubmission();
        $reviewer = User::factory()->create();
        $this->attachToSubmission($reviewer, $submission, ScopedRole::Reviewer->toSlug());

        $this->assertFalse($this->allows($reviewer, SubmissionAbility::UpdateSubmitters, $submission));
    }

    public function testUpdateSubmittersDeniesUnaffiliatedUser(): void
    {
        $this->assertFalse(
            $this->allows(User::factory()->create(), SubmissionAbility::UpdateSubmitters, $this->makeSubmission())
        );
    }

    // ---- updateReviewers ----------------------------------------------------

    public function testUpdateReviewersAllowsAdminAndReviewCoordinator(): void
    {
        $this->assertTrue($this->allows($this->appAdmin(), SubmissionAbility::UpdateReviewers, $this->makeSubmission()));

        $submission = $this->makeSubmission();
        $coordinator = User::factory()->create();
        $this->attachToSubmission($coordinator, $submission, ScopedRole::ReviewCoordinator->toSlug());
        $this->assertTrue($this->allows($coordinator, SubmissionAbility::UpdateReviewers, $submission));
    }

    public function testUpdateReviewersDeniesSubmitterAndReviewer(): void
    {
        foreach ([ScopedRole::Submitter->toSlug(), ScopedRole::Reviewer->toSlug()] as $roleId) {
            $submission = $this->makeSubmission();
            $user = User::factory()->create();
            $this->attachToSubmission($user, $submission, $roleId);

            $this->assertFalse($this->allows($user, SubmissionAbility::UpdateReviewers, $submission), "role_id $roleId");
        }
    }

    // ---- updateReviewCoordinators (admin only) ------------------------------

    public function testUpdateReviewCoordinatorsAllowsAdminRolesOnly(): void
    {
        $this->assertTrue(
            $this->allows($this->appAdmin(), SubmissionAbility::UpdateReviewCoordinators, $this->makeSubmission())
        );

        $submission = $this->makeSubmission();
        $editor = User::factory()->create();
        $this->attachToPublication($editor, $submission->publication, ScopedRole::Editor->toSlug());
        $this->assertTrue($this->allows($editor, SubmissionAbility::UpdateReviewCoordinators, $submission));
    }

    public function testUpdateReviewCoordinatorsDeniesReviewCoordinator(): void
    {
        $submission = $this->makeSubmission();
        $coordinator = User::factory()->create();
        $this->attachToSubmission($coordinator, $submission, ScopedRole::ReviewCoordinator->toSlug());

        $this->assertFalse($this->allows($coordinator, SubmissionAbility::UpdateReviewCoordinators, $submission));
    }

    // ---- updateStatus -------------------------------------------------------

    public function testUpdateStatusAllowsAdminAndReviewCoordinator(): void
    {
        $this->assertTrue($this->allows($this->appAdmin(), SubmissionAbility::UpdateStatus, $this->makeSubmission()));

        $submission = $this->makeSubmission();
        $coordinator = User::factory()->create();
        $this->attachToSubmission($coordinator, $submission, ScopedRole::ReviewCoordinator->toSlug());
        $this->assertTrue($this->allows($coordinator, SubmissionAbility::UpdateStatus, $submission));
    }

    public function testUpdateStatusAllowsSubmitterOnlyWhileDraft(): void
    {
        $draft = $this->makeSubmission(Submission::DRAFT);
        $submitterDraft = User::factory()->create();
        $this->attachToSubmission($submitterDraft, $draft, ScopedRole::Submitter->toSlug());
        $this->assertTrue($this->allows($submitterDraft, SubmissionAbility::UpdateStatus, $draft));

        $submitted = $this->makeSubmission(Submission::INITIALLY_SUBMITTED);
        $submitterSubmitted = User::factory()->create();
        $this->attachToSubmission($submitterSubmitted, $submitted, ScopedRole::Submitter->toSlug());
        $this->assertFalse($this->allows($submitterSubmitted, SubmissionAbility::UpdateStatus, $submitted));
    }

    public function testUpdateStatusDeniesReviewer(): void
    {
        $submission = $this->makeSubmission();
        $reviewer = User::factory()->create();
        $this->attachToSubmission($reviewer, $submission, ScopedRole::Reviewer->toSlug());

        $this->assertFalse($this->allows($reviewer, SubmissionAbility::UpdateStatus, $submission));
    }

    // ---- view (any submission role grants it, via the matrix) ---------------

    public function testViewAllowsAdminAndAnySubmissionRole(): void
    {
        $this->assertTrue($this->allows($this->appAdmin(), SubmissionAbility::View, $this->makeSubmission()));

        $submission = $this->makeSubmission();
        $reviewer = User::factory()->create();
        $this->attachToSubmission($reviewer, $submission, ScopedRole::Reviewer->toSlug());
        $this->assertTrue($this->allows($reviewer, SubmissionAbility::View, $submission));
    }

    public function testViewDeniesUnaffiliatedUser(): void
    {
        $this->assertFalse($this->allows(User::factory()->create(), SubmissionAbility::View, $this->makeSubmission()));
    }

    // ---- updateContent (the work: author-only, draft-only) ------------------

    public function testUpdateContentAllowsSubmitterOnlyWhileDraft(): void
    {
        $draft = $this->makeSubmission(Submission::DRAFT);
        $submitterDraft = User::factory()->create();
        $this->attachToSubmission($submitterDraft, $draft, ScopedRole::Submitter->toSlug());
        $this->assertTrue($this->allows($submitterDraft, SubmissionAbility::UpdateContent, $draft));

        $submitted = $this->makeSubmission(Submission::INITIALLY_SUBMITTED);
        $submitterSubmitted = User::factory()->create();
        $this->attachToSubmission($submitterSubmitted, $submitted, ScopedRole::Submitter->toSlug());
        $this->assertFalse($this->allows($submitterSubmitted, SubmissionAbility::UpdateContent, $submitted));
    }

    public function testUpdateContentDeniesReviewerAndUnaffiliated(): void
    {
        $draft = $this->makeSubmission(Submission::DRAFT);
        $reviewer = User::factory()->create();
        $this->attachToSubmission($reviewer, $draft, ScopedRole::Reviewer->toSlug());

        // The manuscript-edit hole closed: a reviewer holds no `update`.
        $this->assertFalse($this->allows($reviewer, SubmissionAbility::UpdateContent, $draft));
        $this->assertFalse($this->allows(User::factory()->create(), SubmissionAbility::UpdateContent, $draft));
    }

    // ---- review (manuscript access + comment, while reviewable) --------------

    public function testReviewAllowsAnySubmissionRoleOnlyWhileReviewable(): void
    {
        $underReview = $this->makeSubmission(Submission::UNDER_REVIEW);
        $reviewer = User::factory()->create();
        $this->attachToSubmission($reviewer, $underReview, ScopedRole::Reviewer->toSlug());
        $this->assertTrue($this->allows($reviewer, SubmissionAbility::Review, $underReview));

        $draft = $this->makeSubmission(Submission::DRAFT);
        $reviewerDraft = User::factory()->create();
        $this->attachToSubmission($reviewerDraft, $draft, ScopedRole::Reviewer->toSlug());
        $this->assertFalse($this->allows($reviewerDraft, SubmissionAbility::Review, $draft));
    }

    public function testReviewDeniesUnaffiliatedUser(): void
    {
        $this->assertFalse(
            $this->allows(User::factory()->create(), SubmissionAbility::Review, $this->makeSubmission(Submission::UNDER_REVIEW))
        );
    }

    // ---- submit (the author's forward action, draft-only) -------------------

    public function testSubmitAllowsSubmitterOnlyWhileDraft(): void
    {
        $draft = $this->makeSubmission(Submission::DRAFT);
        $submitter = User::factory()->create();
        $this->attachToSubmission($submitter, $draft, ScopedRole::Submitter->toSlug());
        $this->assertTrue($this->allows($submitter, SubmissionAbility::Submit, $draft));

        $submitted = $this->makeSubmission(Submission::INITIALLY_SUBMITTED);
        $submitterSubmitted = User::factory()->create();
        $this->attachToSubmission($submitterSubmitted, $submitted, ScopedRole::Submitter->toSlug());
        $this->assertFalse($this->allows($submitterSubmitted, SubmissionAbility::Submit, $submitted));
    }

    public function testSubmitDeniesReviewerAndCoordinator(): void
    {
        foreach ([ScopedRole::Reviewer->toSlug(), ScopedRole::ReviewCoordinator->toSlug()] as $roleId) {
            $draft = $this->makeSubmission(Submission::DRAFT);
            $user = User::factory()->create();
            $this->attachToSubmission($user, $draft, $roleId);

            $this->assertFalse($this->allows($user, SubmissionAbility::Submit, $draft), "role_id $roleId");
        }
    }

    // ---- comment edit/delete (CommentAbility, author-while-reviewable) -------
    // Resolved against the comment through its owning submission; the
    // OwnsCommentWhileReviewable predicate ties the grant to authorship and the
    // review window. The @scopedCan(ability: "CommentAbility::...") fields call
    // exactly this.

    private function reviewerOn(Submission $submission): User
    {
        $user = User::factory()->create();
        $this->attachToSubmission($user, $submission, ScopedRole::Reviewer->toSlug());

        return $user;
    }

    private function inlineBy(Submission $submission, User $author): InlineComment
    {
        return InlineComment::withoutEvents(fn() => InlineComment::factory()->create([
            'submission_id' => $submission->id,
            'created_by' => $author->id,
            'updated_by' => $author->id,
            'style_criteria' => [],
        ]));
    }

    public function testCommentAuthorMayUpdateAndDeleteOwnWhileReviewable(): void
    {
        $submission = $this->makeSubmission(Submission::UNDER_REVIEW);
        $author = $this->reviewerOn($submission);
        $comment = $this->inlineBy($submission, $author);

        $this->assertTrue($this->resolver->allows($author, CommentAbility::Update, $comment));
        $this->assertTrue($this->resolver->allows($author, CommentAbility::Delete, $comment));
    }

    public function testCommentAuthorLosesEditOnceReviewCloses(): void
    {
        $submission = $this->makeSubmission(Submission::AWAITING_DECISION);
        $author = $this->reviewerOn($submission);
        $comment = $this->inlineBy($submission, $author);

        $this->assertFalse($this->resolver->allows($author, CommentAbility::Update, $comment));
        $this->assertFalse($this->resolver->allows($author, CommentAbility::Delete, $comment));
    }

    public function testNonAuthorCannotEditOrDeleteAnothersComment(): void
    {
        $submission = $this->makeSubmission(Submission::UNDER_REVIEW);
        $author = $this->reviewerOn($submission);
        $comment = $this->inlineBy($submission, $author);
        $other = $this->reviewerOn($submission);

        $this->assertFalse($this->resolver->allows($other, CommentAbility::Update, $comment));
        $this->assertFalse($this->resolver->allows($other, CommentAbility::Delete, $comment));
    }

    public function testApplicationAdministratorModeratesAnyComment(): void
    {
        // Non-author, past the review window — the admin role short-circuits both.
        $submission = $this->makeSubmission(Submission::AWAITING_DECISION);
        $author = $this->reviewerOn($submission);
        $comment = $this->inlineBy($submission, $author);

        $this->assertTrue($this->resolver->allows($this->appAdmin(), CommentAbility::Update, $comment));
        $this->assertTrue($this->resolver->allows($this->appAdmin(), CommentAbility::Delete, $comment));
    }

    public function testCommentAbilitiesApplyToOverallCommentsToo(): void
    {
        $submission = $this->makeSubmission(Submission::UNDER_REVIEW);
        $author = $this->reviewerOn($submission);
        $comment = OverallComment::withoutEvents(fn() => OverallComment::factory()->create([
            'submission_id' => $submission->id,
            'created_by' => $author->id,
            'updated_by' => $author->id,
        ]));

        $this->assertTrue($this->resolver->allows($author, CommentAbility::Update, $comment));
        $this->assertFalse(
            $this->resolver->allows($this->reviewerOn($submission), CommentAbility::Update, $comment)
        );
    }
}
