<?php

namespace Afterburner\Voting\Jobs;

use Afterburner\Voting\Actions\OpenBallot;
use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Models\Ballot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OpenScheduledBallot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $ballotId) {}

    public function handle(OpenBallot $openBallot): void
    {
        if (! config('afterburner-voting.schedule_transitions', true)) {
            return;
        }

        $ballot = Ballot::query()->find($this->ballotId);

        if (! $ballot || $ballot->status !== BallotStatus::Scheduled) {
            return;
        }

        if ($ballot->opens_at && $ballot->opens_at->isFuture()) {
            return;
        }

        try {
            $openBallot->execute($ballot);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
