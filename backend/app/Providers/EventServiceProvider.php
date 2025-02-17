<?php

namespace App\Providers;

use App\Events\ReviewCoordinatorInvitationAccepted;
use App\Events\ReviewCoordinatorInvited;
use App\Events\ReviewerInvited;
use App\Events\ReviewerInvitationAccepted;
use App\Events\SubmissionStatusUpdated;
use App\Listeners\NotifyReviewCoordinatorAboutInvitation;
use App\Listeners\NotifyReviewerAboutInvitation;
use App\Listeners\NotifyUsersAboutAcceptedReviewCoordinatorInvitation;
use App\Listeners\NotifyUsersAboutAcceptedReviewerInvitation;
use App\Listeners\NotifyUsersAboutUpdatedSubmissionStatus;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        SubmissionStatusUpdated::class => [
            NotifyUsersAboutUpdatedSubmissionStatus::class,
        ],
        ReviewerInvited::class => [
            NotifyReviewerAboutInvitation::class,
        ],
        ReviewCoordinatorInvited::class => [
            NotifyReviewCoordinatorAboutInvitation::class,
        ],
        ReviewerInvitationAccepted::class => [
            NotifyUsersAboutAcceptedReviewerInvitation::class,
        ],
        ReviewCoordinatorInvitationAccepted::class => [
            NotifyUsersAboutAcceptedReviewCoordinatorInvitation::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
