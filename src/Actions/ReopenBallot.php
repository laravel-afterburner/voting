<?php

namespace Afterburner\Voting\Actions;

use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Events\BallotReopened;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\Ballot;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ReopenBallot
{
    public function execute(Ballot $ballot, ?User $user = null): Ballot
    {
        if ($user !== null) {
            Gate::forUser($user)->authorize('reopen', $ballot);
        }

        if ($ballot->status !== BallotStatus::Closed) {
            throw new VotingException('Only closed ballots can be reopened.');
        }

        $updates = [
            'status' => BallotStatus::Open,
            'closed_at' => null,
        ];

        if ($ballot->closes_at && $ballot->closes_at->isPast()) {
            $updates['closes_at'] = null;
        }

        $ballot->update($updates);

        $ballot = $ballot->fresh(['options']);

        BallotReopened::dispatch($ballot, $user?->id);

        return $ballot;
    }
}
