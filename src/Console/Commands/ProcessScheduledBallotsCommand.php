<?php

namespace Afterburner\Voting\Console\Commands;

use Afterburner\Voting\Actions\CloseBallot;
use Afterburner\Voting\Actions\OpenBallot;
use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Models\Ballot;
use Illuminate\Console\Command;

class ProcessScheduledBallotsCommand extends Command
{
    protected $signature = 'afterburner:voting:process-scheduled';

    protected $description = 'Open scheduled ballots and close ballots past their close time';

    public function handle(OpenBallot $openBallot, CloseBallot $closeBallot): int
    {
        if (! config('afterburner-voting.schedule_transitions', true)) {
            $this->warn('Scheduled ballot transitions are disabled.');

            return self::SUCCESS;
        }

        $opened = 0;
        $closed = 0;

        Ballot::query()
            ->where('status', BallotStatus::Scheduled)
            ->whereNotNull('opens_at')
            ->where('opens_at', '<=', now())
            ->each(function (Ballot $ballot) use ($openBallot, &$opened) {
                try {
                    $openBallot->execute($ballot);
                    $opened++;
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });

        Ballot::query()
            ->where('status', BallotStatus::Open)
            ->whereNotNull('closes_at')
            ->where('closes_at', '<=', now())
            ->each(function (Ballot $ballot) use ($closeBallot, &$closed) {
                try {
                    $closeBallot->execute($ballot);
                    $closed++;
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });

        $this->info("Opened {$opened} ballot(s), closed {$closed} ballot(s).");

        return self::SUCCESS;
    }
}
