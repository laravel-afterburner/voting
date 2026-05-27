<?php

namespace Afterburner\Voting\Actions;

use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Events\BallotClosed;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\Ballot;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CloseBallot
{
    public function execute(Ballot $ballot, ?User $user = null): Ballot
    {
        if ($user !== null) {
            Gate::forUser($user)->authorize('close', $ballot);
        }

        if ($ballot->status !== BallotStatus::Open) {
            throw new VotingException('Only open ballots can be closed.');
        }

        $ballot->update([
            'status' => BallotStatus::Closed,
            'closed_at' => now(),
        ]);

        $ballot = $ballot->fresh(['options']);

        BallotClosed::dispatch($ballot);

        return $ballot;
    }
}
