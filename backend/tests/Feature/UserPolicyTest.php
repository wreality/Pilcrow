<?php
declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\Roles\GlobalRole;
use App\Auth\Roles\ScopedRole;
use App\Models\Publication;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function attachToPublication(User $user, Publication $publication, string $roleId): void
    {
        $user->publications()->attach($publication->id, ['role' => $roleId]);
    }

    public function testViewEmailAllowsSelf(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->can('viewEmail', $user));
    }

    public function testViewEmailAllowsApplicationAdministrator(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(GlobalRole::ApplicationAdministrator);
        $target = User::factory()->create();

        $this->assertTrue($admin->can('viewEmail', $target));
    }

    public function testViewEmailAllowsPublicationAdminForUserInSamePublication(): void
    {
        $publication = Publication::factory()->create();
        $admin = User::factory()->create();
        $target = User::factory()->create();

        $this->attachToPublication($admin, $publication, ScopedRole::PublicationAdmin->toSlug());
        $this->attachToPublication($target, $publication, ScopedRole::Editor->toSlug());

        $this->assertTrue($admin->can('viewEmail', $target));
    }

    public function testViewEmailAllowsEditorForUserInSamePublication(): void
    {
        $publication = Publication::factory()->create();
        $editor = User::factory()->create();
        $target = User::factory()->create();

        $this->attachToPublication($editor, $publication, ScopedRole::Editor->toSlug());
        $this->attachToPublication($target, $publication, ScopedRole::Editor->toSlug());

        $this->assertTrue($editor->can('viewEmail', $target));
    }

    public function testViewEmailAllowsEditorForSubmitterInTheirPublication(): void
    {
        $publication = Publication::factory()->create();
        $editor = User::factory()->create();
        $this->attachToPublication($editor, $publication, ScopedRole::Editor->toSlug());

        $submitter = User::factory()->create();
        Submission::factory()
            ->for($publication)
            ->hasAttached($submitter, [], 'submitters')
            ->create();

        $this->assertTrue($editor->can('viewEmail', $submitter));
    }

    public function testViewEmailDeniesReviewer(): void
    {
        $publication = Publication::factory()->create();
        $reviewer = User::factory()->create();
        $coAuthor = User::factory()->create();

        Submission::factory()
            ->for($publication)
            ->hasAttached($reviewer, [], 'reviewers')
            ->hasAttached($coAuthor, [], 'submitters')
            ->create();

        $this->assertFalse($reviewer->can('viewEmail', $coAuthor));
    }

    public function testViewEmailAllowsReviewCoordinatorForReviewTeamMembers(): void
    {
        $publication = Publication::factory()->create();
        $coordinator = User::factory()->create();
        $reviewer = User::factory()->create();
        $fellowCoordinator = User::factory()->create();

        Submission::factory()
            ->for($publication)
            ->hasAttached($coordinator, [], 'reviewCoordinators')
            ->hasAttached($fellowCoordinator, [], 'reviewCoordinators')
            ->hasAttached($reviewer, [], 'reviewers')
            ->create();

        $this->assertTrue($coordinator->can('viewEmail', $reviewer));
        $this->assertTrue($coordinator->can('viewEmail', $fellowCoordinator));
    }

    public function testViewEmailDeniesReviewCoordinatorForReviewerOfOtherSubmission(): void
    {
        $publication = Publication::factory()->create();
        $coordinator = User::factory()->create();
        $reviewer = User::factory()->create();

        Submission::factory()
            ->for($publication)
            ->hasAttached($coordinator, [], 'reviewCoordinators')
            ->create();

        Submission::factory()
            ->for($publication)
            ->hasAttached($reviewer, [], 'reviewers')
            ->create();

        $this->assertFalse($coordinator->can('viewEmail', $reviewer));
    }

    public function testViewEmailDeniesReviewCoordinator(): void
    {
        $publication = Publication::factory()->create();
        $coordinator = User::factory()->create();
        $other = User::factory()->create();

        Submission::factory()
            ->for($publication)
            ->hasAttached($coordinator, [], 'reviewCoordinators')
            ->hasAttached($other, [], 'submitters')
            ->create();

        $this->assertFalse($coordinator->can('viewEmail', $other));
    }

    public function testViewEmailDeniesReviewerForFellowReviewTeamMembers(): void
    {
        $publication = Publication::factory()->create();
        $reviewer = User::factory()->create();
        $fellowReviewer = User::factory()->create();
        $coordinator = User::factory()->create();

        Submission::factory()
            ->for($publication)
            ->hasAttached($reviewer, [], 'reviewers')
            ->hasAttached($fellowReviewer, [], 'reviewers')
            ->hasAttached($coordinator, [], 'reviewCoordinators')
            ->create();

        $this->assertFalse($reviewer->can('viewEmail', $fellowReviewer));
        $this->assertFalse($reviewer->can('viewEmail', $coordinator));
    }

    public function testViewEmailDeniesReviewCoordinatorForSubmitterWhoReviewsElsewhere(): void
    {
        // The target submits to the coordinated submission and reviews a
        // different one. The role and the submission must match on the same
        // assignment, so this stays redacted.
        $publication = Publication::factory()->create();
        $coordinator = User::factory()->create();
        $target = User::factory()->create();

        Submission::factory()
            ->for($publication)
            ->hasAttached($coordinator, [], 'reviewCoordinators')
            ->hasAttached($target, [], 'submitters')
            ->create();

        Submission::factory()
            ->for($publication)
            ->hasAttached($target, [], 'reviewers')
            ->create();

        $this->assertFalse($coordinator->can('viewEmail', $target));
    }

    public function testViewEmailDeniesFormerReviewCoordinator(): void
    {
        $publication = Publication::factory()->create();
        $coordinator = User::factory()->create();
        $reviewer = User::factory()->create();

        $submission = Submission::factory()
            ->for($publication)
            ->hasAttached($coordinator, [], 'reviewCoordinators')
            ->hasAttached($reviewer, [], 'reviewers')
            ->create();

        $this->assertTrue($coordinator->can('viewEmail', $reviewer));

        $submission->reviewCoordinators()->detach($coordinator->id);

        // The coordinated-submission lookup is memoized per viewer instance,
        // so a fresh instance (a new request) sees the removal.
        $this->assertFalse($coordinator->fresh()->can('viewEmail', $reviewer));
    }

    public function testViewEmailMemoizesViewerCoordinatedSubmissionLookup(): void
    {
        $publication = Publication::factory()->create();
        $coordinator = User::factory()->create();
        $reviewers = User::factory()->count(5)->create();

        $factory = Submission::factory()
            ->for($publication)
            ->hasAttached($coordinator, [], 'reviewCoordinators');
        foreach ($reviewers as $reviewer) {
            $factory = $factory->hasAttached($reviewer, [], 'reviewers');
        }
        $factory->create();

        $coordinator->isApplicationAdministrator();

        DB::enableQueryLog();
        foreach ($reviewers as $reviewer) {
            $this->assertTrue($coordinator->can('viewEmail', $reviewer));
        }
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        // The viewer's coordinated-submission lookup is the only query that
        // plucks submission ids; it runs once across all five checks.
        $viewerSubmissionLookups = $queries->filter(
            fn(string $sql) => str_starts_with($sql, 'select `submissions`.`id`')
        );
        $this->assertCount(1, $viewerSubmissionLookups);
    }

    public function testViewEmailDeniesEditorOfDifferentPublication(): void
    {
        $editor = User::factory()->create();
        $this->attachToPublication($editor, Publication::factory()->create(), ScopedRole::Editor->toSlug());

        $other = User::factory()->create();
        $this->attachToPublication($other, Publication::factory()->create(), ScopedRole::Editor->toSlug());

        $this->assertFalse($editor->can('viewEmail', $other));
    }

    public function testViewAllowsOnlyApplicationAdministrator(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(GlobalRole::ApplicationAdministrator);
        $other = User::factory()->create();

        $this->assertTrue($admin->can('view', $other));
        $this->assertFalse($other->can('view', $other));
        $this->assertFalse($other->can('view', $admin));
    }

    public function testViewEmailDeniesUnaffiliatedUser(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();

        $this->assertFalse($viewer->can('viewEmail', $target));
    }

    public function testViewEmailMemoizesViewerPublicationLookup(): void
    {
        $publication = Publication::factory()->create();
        $editor = User::factory()->create();
        $this->attachToPublication($editor, $publication, ScopedRole::Editor->toSlug());

        $targets = User::factory()->count(5)->create();
        foreach ($targets as $target) {
            $this->attachToPublication($target, $publication, ScopedRole::Editor->toSlug());
        }

        // Prime the application-admin role lookup so it doesn't pollute the count.
        $editor->isApplicationAdministrator();

        DB::enableQueryLog();
        foreach ($targets as $target) {
            $this->assertTrue($editor->can('viewEmail', $target));
        }
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        // Viewer's privileged-publication lookup hits the publication_user
        // pivot exactly once even though the policy was invoked 5 times.
        $viewerPubLookups = $queries->filter(
            fn(string $sql) => str_contains($sql, 'publication_user')
                && str_contains($sql, 'role')
        );
        $this->assertCount(1, $viewerPubLookups);
    }
}
