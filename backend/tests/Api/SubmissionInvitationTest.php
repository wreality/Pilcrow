<?php
declare(strict_types=1);

namespace Tests\Api;

use App\Auth\Roles\ScopedRole;
use App\Models\Submission;
use App\Models\SubmissionInvitation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ApiTestCase;

class SubmissionInvitationTest extends ApiTestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    public function testValidEmailsCanBeProvidedInAnInvitation()
    {
        $valid_email = 'test@gmail.com';
        $this->beAppAdmin();
        $submission = Submission::factory()->create();
        $response = $this->graphQL(
            'mutation InviteReviewer ($submission_id: ID! $email: String! $message: String){
                inviteReviewer(input: {
                  submission_id: $submission_id
                  email: $email
                  message: $message
                }) {
                  id
                  reviewers {
                    email
                    staged
                  }
                }
              }
            ',
            [
                'submission_id' => $submission->id,
                'email' => $valid_email,
                'message' => '',
            ]
        );
        $expected_data = [
            'inviteReviewer' => [
                'id' => (string)$submission->id,
                'reviewers' => [
                    [
                        'email' => $valid_email,
                        'staged' => true,
                    ],
                ],
            ],
        ];
        $response->assertJsonPath('data', $expected_data);
    }

    /**
     * Inviting a review coordinator stages the user, attaches them with the
     * review_coordinator role, and persists the invitation with the slug (and
     * its legacy id for rollback safety).
     *
     * @return void
     */
    public function testReviewCoordinatorCanBeInvited()
    {
        $valid_email = 'coordinator@gmail.com';
        $this->beAppAdmin();
        $submission = Submission::factory()->create();
        $response = $this->graphQL(
            'mutation InviteReviewCoordinator ($submission_id: ID! $email: String! $message: String){
                inviteReviewCoordinator(input: {
                  submission_id: $submission_id
                  email: $email
                  message: $message
                }) {
                  id
                  review_coordinators {
                    email
                    staged
                  }
                }
              }
            ',
            [
                'submission_id' => $submission->id,
                'email' => $valid_email,
                'message' => '',
            ]
        );

        $response->assertJsonPath('data.inviteReviewCoordinator.review_coordinators', [
            ['email' => $valid_email, 'staged' => true],
        ]);
        $this->assertDatabaseHas('submission_invitations', [
            'submission_id' => $submission->id,
            'email' => $valid_email,
            'role' => ScopedRole::ReviewCoordinator->toSlug(),
            'role_id' => ScopedRole::ReviewCoordinator->legacyId(),
        ]);
    }

    /**
     * Call a reinvite mutation for the given user.
     *
     * @param string $mutation reinviteReviewer or reinviteReviewCoordinator
     * @param \App\Models\Submission $submission
     * @param \App\Models\User $user
     * @return \Illuminate\Testing\TestResponse
     */
    private function callReinvite(string $mutation, Submission $submission, User $user): TestResponse
    {
        return $this->graphQL(
            "mutation Reinvite (\$submission_id: ID! \$user_id: ID! \$message: String){
                {$mutation}(input: {
                  submission_id: \$submission_id
                  user_id: \$user_id
                  message: \$message
                }) {
                  id
                }
              }
            ",
            [
                'submission_id' => $submission->id,
                'user_id' => $user->id,
                'message' => '',
            ]
        );
    }

    /**
     * Stage a user on the submission in the given role, as an invite does.
     *
     * @param \App\Models\Submission $submission
     * @param \App\Auth\Roles\ScopedRole $role
     * @param string $email
     * @return \App\Models\User
     */
    private function stageInvitee(Submission $submission, ScopedRole $role, string $email): User
    {
        $invitation = SubmissionInvitation::create([
            'submission_id' => $submission->id,
            'role' => $role->toSlug(),
            'email' => $email,
        ]);
        $role === ScopedRole::Reviewer
            ? $invitation->inviteReviewer()
            : $invitation->inviteReviewCoordinator();

        return User::where('email', $email)->firstOrFail();
    }

    /**
     * Re-inviting a reviewer resends the invitation to the staged user's stored
     * email without staging a duplicate user, and records the reviewer role
     * slug + legacy id.
     *
     * @return void
     */
    public function testReviewerCanBeReinvited()
    {
        $email = 'reinvited.reviewer@gmail.com';
        $this->beAppAdmin();
        $submission = Submission::factory()->create();
        $staged = $this->stageInvitee($submission, ScopedRole::Reviewer, $email);

        $response = $this->callReinvite('reinviteReviewer', $submission, $staged);

        $response->assertJsonPath('data.reinviteReviewer.id', (string)$submission->id);
        $this->assertSame(2, SubmissionInvitation::where('email', $email)->count());
        $this->assertDatabaseHas('submission_invitations', [
            'submission_id' => $submission->id,
            'email' => $email,
            'role' => ScopedRole::Reviewer->toSlug(),
            'role_id' => ScopedRole::Reviewer->legacyId(),
        ]);
        $this->assertSame(1, User::where('email', $email)->count());
    }

    /**
     * Re-inviting a review coordinator resends the invitation and records the
     * review_coordinator role slug + legacy id.
     *
     * @return void
     */
    public function testReviewCoordinatorCanBeReinvited()
    {
        $email = 'reinvited.coordinator@gmail.com';
        $this->beAppAdmin();
        $submission = Submission::factory()->create();
        $staged = $this->stageInvitee($submission, ScopedRole::ReviewCoordinator, $email);

        $response = $this->callReinvite('reinviteReviewCoordinator', $submission, $staged);

        $response->assertJsonPath('data.reinviteReviewCoordinator.id', (string)$submission->id);
        $this->assertSame(2, SubmissionInvitation::where('email', $email)->count());
        $this->assertDatabaseHas('submission_invitations', [
            'submission_id' => $submission->id,
            'email' => $email,
            'role' => ScopedRole::ReviewCoordinator->toSlug(),
            'role_id' => ScopedRole::ReviewCoordinator->legacyId(),
        ]);
    }

    /**
     * A review coordinator can reinvite a staged reviewer on a submission they
     * coordinate, without being able to read the reviewer's email.
     *
     * @return void
     */
    public function testReviewCoordinatorCanReinviteStagedReviewer()
    {
        $email = 'coordinator.reinvites@gmail.com';
        $coordinator = $this->beReviewCoordinator();
        $submission = $coordinator->submissions()->firstOrFail();
        $staged = $this->stageInvitee($submission, ScopedRole::Reviewer, $email);

        $this->assertFalse($coordinator->can('viewEmail', $staged));

        $response = $this->callReinvite('reinviteReviewer', $submission, $staged);

        $response->assertJsonPath('data.reinviteReviewer.id', (string)$submission->id);
        $this->assertSame(2, SubmissionInvitation::where('email', $email)->count());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function reinviteMutationProvider(): array
    {
        return [
            'reviewer' => ['reinviteReviewer'],
            'review coordinator' => ['reinviteReviewCoordinator'],
        ];
    }

    /**
     * A confirmed (not staged) member of the team cannot be reinvited.
     *
     * @param string $mutation
     * @return void
     */
    #[DataProvider('reinviteMutationProvider')]
    public function testConfirmedUserCannotBeReinvited(string $mutation)
    {
        $this->beAppAdmin();
        $member = User::factory()->create();
        $relation = $mutation === 'reinviteReviewer' ? 'reviewers' : 'reviewCoordinators';
        $submission = Submission::factory()
            ->hasAttached($member, [], $relation)
            ->create();

        $response = $this->callReinvite($mutation, $submission, $member);

        $response->assertGraphQLErrorMessage('The user is not a pending invitee of this submission in this role.');
        $this->assertSame(0, SubmissionInvitation::where('email', $member->email)->count());
    }

    /**
     * A staged user of a different submission cannot be reinvited through
     * this one, so a reinvite can't be aimed at an arbitrary account.
     *
     * @param string $mutation
     * @return void
     */
    #[DataProvider('reinviteMutationProvider')]
    public function testStagedUserOfAnotherSubmissionCannotBeReinvited(string $mutation)
    {
        $email = 'elsewhere@gmail.com';
        $this->beAppAdmin();
        $submission = Submission::factory()->create();
        $role = $mutation === 'reinviteReviewer' ? ScopedRole::Reviewer : ScopedRole::ReviewCoordinator;
        $staged = $this->stageInvitee(Submission::factory()->create(), $role, $email);

        $response = $this->callReinvite($mutation, $submission, $staged);

        $response->assertGraphQLErrorMessage('The user is not a pending invitee of this submission in this role.');
        $this->assertSame(1, SubmissionInvitation::where('email', $email)->count());
    }

    /**
     * A staged reviewer cannot be reinvited as a review coordinator, and vice
     * versa.
     *
     * @return void
     */
    public function testStagedUserCannotBeReinvitedInAnotherRole()
    {
        $this->beAppAdmin();
        $submission = Submission::factory()->create();
        $reviewer = $this->stageInvitee($submission, ScopedRole::Reviewer, 'staged.rev@gmail.com');
        $coordinator = $this->stageInvitee($submission, ScopedRole::ReviewCoordinator, 'staged.coord@gmail.com');

        $this->callReinvite('reinviteReviewCoordinator', $submission, $reviewer)
            ->assertGraphQLErrorMessage('The user is not a pending invitee of this submission in this role.');
        $this->callReinvite('reinviteReviewer', $submission, $coordinator)
            ->assertGraphQLErrorMessage('The user is not a pending invitee of this submission in this role.');
    }

    /**
     * A reviewer has no ability to reinvite anyone, even a staged teammate.
     *
     * @return void
     */
    public function testReviewerCannotReinvite()
    {
        $reviewer = $this->beReviewer();
        $submission = $reviewer->submissions()->firstOrFail();
        $staged = $this->stageInvitee($submission, ScopedRole::Reviewer, 'teammate@gmail.com');

        $response = $this->callReinvite('reinviteReviewer', $submission, $staged);

        $response->assertGraphQLErrorMessage('UNAUTHORIZED');
        $this->assertSame(1, SubmissionInvitation::where('email', 'teammate@gmail.com')->count());
    }

    /**
     * A submission may have at most one review coordinator. Inviting a second
     * (different) coordinator through the API is rejected with a typed error and
     * does not create an invitation, leaving the original coordinator intact.
     *
     * @return void
     */
    public function testCannotInviteASecondReviewCoordinator()
    {
        $this->beAppAdmin();
        $submission = Submission::factory()->create();

        // First coordinator is staged + attached.
        SubmissionInvitation::create([
            'submission_id' => $submission->id,
            'role' => ScopedRole::ReviewCoordinator->toSlug(),
            'email' => 'first.coordinator@gmail.com',
        ])->inviteReviewCoordinator();

        $response = $this->graphQL(
            'mutation InviteReviewCoordinator ($submission_id: ID! $email: String! $message: String){
                inviteReviewCoordinator(input: {
                  submission_id: $submission_id
                  email: $email
                  message: $message
                }) {
                  id
                }
              }
            ',
            [
                'submission_id' => $submission->id,
                'email' => 'second.coordinator@gmail.com',
                'message' => '',
            ]
        );

        $response->assertJsonPath('errors.0.extensions.code', 'SUBMISSION_ALREADY_HAS_COORDINATOR');
        // The rejected invite is pre-empted before any row is written.
        $this->assertDatabaseMissing('submission_invitations', [
            'email' => 'second.coordinator@gmail.com',
        ]);
        $this->assertCount(1, $submission->fresh()->reviewCoordinators);
    }

    /**
     * The coordinator-uniqueness guard must not over-block: a reviewer can still
     * be invited to a submission that already has a (different) coordinator.
     *
     * @return void
     */
    public function testReviewerCanStillBeInvitedWhenCoordinatorExists()
    {
        $this->beAppAdmin();
        $submission = Submission::factory()->create();

        SubmissionInvitation::create([
            'submission_id' => $submission->id,
            'role' => ScopedRole::ReviewCoordinator->toSlug(),
            'email' => 'the.coordinator@gmail.com',
        ])->inviteReviewCoordinator();

        $response = $this->graphQL(
            'mutation InviteReviewer ($submission_id: ID! $email: String! $message: String){
                inviteReviewer(input: {
                  submission_id: $submission_id
                  email: $email
                  message: $message
                }) {
                  id
                  reviewers { email staged }
                }
              }
            ',
            [
                'submission_id' => $submission->id,
                'email' => 'a.reviewer@gmail.com',
                'message' => '',
            ]
        );

        $response->assertJsonPath('data.inviteReviewer.reviewers', [
            ['email' => 'a.reviewer@gmail.com', 'staged' => true],
        ]);
        $this->assertNull($response->json('errors'));
    }

    /**
     * @return array
     */
    public static function invalidEmailsProvider(): array
    {
        return [
            'no domain' => ['invalidemail'],
            'fake domain' => ['test@madeupdomainfortesting.com'],
            'two @ symbols' => ['test@test@gmail.com'],
        ];
    }

    /**
     * @return void
     */
    #[DataProvider('invalidEmailsProvider')]
    public function testInvalidEmailsCannotBeProvidedInAnInvitation(string $email)
    {
        $this->beAppAdmin();
        $submission = Submission::factory()->create();
        $response = $this->graphQL(
            'mutation InviteReviewer ($submission_id: ID! $email: String! $message: String){
                inviteReviewer(input: {
                  submission_id: $submission_id
                  email: $email
                  message: $message
                }) {
                  id
                  reviewers {
                    email
                    staged
                  }
                }
              }
            ',
            [
                'submission_id' => $submission->id,
                'email' => $email,
                'message' => '',
            ]
        );
        $responseMessage = $response->json('errors.0.message');
        $this->assertStringStartsWith('Validation failed', $responseMessage);
    }

    /**
     * @param array $variables
     * @return \Illuminate\Testing\TestResponse
     */
    public function callAcceptSubmissionInvite(array $variables): TestResponse
    {
        return $this->graphQL('
            mutation AcceptSubmissionInvite(
                $uuid: String!
                $token: String!
                $expires: String!
                $id: ID!
                $name: String
                $username: String!
                $password: String!
            ) {
                acceptSubmissionInvite(
                    uuid: $uuid
                    token: $token
                    expires: $expires
                    user: { id: $id, name: $name, username: $username, password: $password }
                ) {
                    id
                    name
                    email
                    username
                }
            }
        ', $variables);
    }

    /**
     * @return void
     */
    public function testCanAcceptAnInviteToASubmission(): void
    {
        $this->beAppAdmin();
        $submission = Submission::factory()->create();
        $invite = SubmissionInvitation::create([
            'submission_id' => $submission->id,
            'role' => ScopedRole::Reviewer->toSlug(),
            'email' => 'mesh@msu.edu',
        ]);
        $invite->inviteReviewer();

        $expires = (string)Carbon::now()->addMinutes(10)->timestamp;
        $token = $invite->makeToken($expires);

        $params = [
            'uuid' => $invite->uuid,
            'token' => $token,
            'expires' => $expires,
            'id' => $invite->invitee->id,
            'name' => '',
            'username' => 'MeshReviewer',
            'password' => 'ImTheMeshReviewerAndThisIsMyPassword!@#',
        ];

        $response = $this->callAcceptSubmissionInvite($params);

        $this->assertNotNull(Arr::get($response, 'data.acceptSubmissionInvite'));
        $this->assertNull(Arr::get($response, 'errors'));
    }

    /**
     * @return array
     */
    public static function variablesProvider(): array
    {
        return [
            'missing username' => [
                [
                    'username' => '',
                ],
            ],
            'missing password' => [
                [
                    'password' => '',
                ],
            ],
            'weak password' => [
                [
                    'password' => 'password123',
                ],
            ],
            'missing expires' => [
                [
                    'expires' => '',
                ],
            ],
            'expired' => [
                [
                    'expires' => (string)Carbon::now()->subMinutes(30)->timestamp,
                ],
            ],
            'missing uuid' => [
                [
                    'uuid' => '',
                ],
            ],
            'invalid uuid' => [
                [
                    'uuid' => '1234567890',
                ],
            ],
            'incorrect uuid' => [
                [
                    'uuid' => Str::uuid()->toString(),
                ],
            ],
            'missing token' => [
                [
                    'token' => '',
                ],
            ],
            'invalid token' => [
                [
                    'token' => '1234567890',
                ],
            ],
            'incorrect token' => [
                [
                    'token' => hash_hmac(
                        'sha256',
                        '20000#email@msu.edu#1671768239',
                        'APP_KEY',
                    ),
                ],
            ],
        ];
    }

    /**
     * @param array $variable
     * @return void
     */
    #[DataProvider('variablesProvider')]
    public function testBadInputsForTheAcceptanceMutationResultInFailures(array $variable): void
    {
        $this->beAppAdmin();
        $submission = Submission::factory()->create();
        $invite = SubmissionInvitation::create([
            'submission_id' => $submission->id,
            'role' => ScopedRole::Reviewer->toSlug(),
            'email' => 'mesh@msu.edu',
        ]);
        $invite->inviteReviewer();

        $expires = $variable['expires'] ?? (string)Carbon::now()->addMinutes(10)->timestamp;
        $token = $variable['token'] ?? $invite->makeToken($expires);

        $params = [
            'uuid' => $variable['uuid'] ?? $invite->uuid,
            'token' => $variable['token'] ?? $token,
            'expires' => $variable['expires'] ?? $expires,
            'id' => $variable['id'] ?? $invite->invitee->id,
            'name' => '',
            'username' => $variable['username'] ?? 'MeshReviewer',
            'password' => $variable['password'] ?? 'ImTheMeshReviewerAndThisIsMyPassword!@#',
        ];

        $response = $this->callAcceptSubmissionInvite($params);

        $this->assertNotNull(Arr::get($response, 'errors'));
    }
}
