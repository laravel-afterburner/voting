<?php

namespace Afterburner\Voting\Listeners;

use Afterburner\Voting\Events\BallotPublished;

/**
 * Stub listener for ballot-published voter notifications.
 *
 * The voting package does not send email or in-app notifications. Host apps
 * should subscribe to BallotPublished (or extend this listener) to notify
 * eligible voters when a ballot opens.
 */
class SendBallotPublishedVoterNotifications
{
    public function handle(BallotPublished $event): void
    {
        // Intentionally empty — implement notification delivery in the host app.
    }
}
