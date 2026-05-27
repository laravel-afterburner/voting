<?php

namespace Afterburner\Voting\Support;

use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Jobs\CloseScheduledBallot;
use Afterburner\Voting\Jobs\OpenScheduledBallot;
use Afterburner\Voting\Models\Ballot;

class ScheduleBallotTransitions
{
    public static function dispatchFor(Ballot $ballot): void
    {
        if (! config('afterburner-voting.schedule_transitions', true)) {
            return;
        }

        if ($ballot->status === BallotStatus::Scheduled && $ballot->opens_at) {
            OpenScheduledBallot::dispatch($ballot->id)
                ->delay($ballot->opens_at);
        }

        if (in_array($ballot->status, [BallotStatus::Scheduled, BallotStatus::Open], true) && $ballot->closes_at) {
            CloseScheduledBallot::dispatch($ballot->id)
                ->delay($ballot->closes_at);
        }
    }
}
