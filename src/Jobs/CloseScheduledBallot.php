<?php

namespace Afterburner\Voting\Jobs;

use Afterburner\Voting\Actions\CloseBallot;
use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Models\Ballot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CloseScheduledBallot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $ballotId) {}

    public function handle(CloseBallot $closeBallot): void
    {
        if (! config('afterburner-voting.schedule_transitions', true)) {
            return;
        }

        $ballot = Ballot::query()->find($this->ballotId);

        if (! $ballot || $ballot->status !== BallotStatus::Open) {
            return;
        }

        if ($ballot->closes_at && $ballot->closes_at->isFuture()) {
            return;
        }

        try {
            $closeBallot->execute($ballot);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
