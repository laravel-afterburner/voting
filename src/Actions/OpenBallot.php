<?php

namespace Afterburner\Voting\Actions;

use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Events\BallotOpened;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\Ballot;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class OpenBallot
{
    public function execute(Ballot $ballot, ?User $user = null): Ballot
    {
        if ($user !== null) {
            Gate::forUser($user)->authorize('publish', $ballot);
        }

        if ($ballot->status !== BallotStatus::Scheduled) {
            throw new VotingException('Only scheduled ballots can be opened.');
        }

        if ($ballot->opens_at && $ballot->opens_at->isFuture()) {
            throw new VotingException('This ballot is not yet scheduled to open.');
        }

        $ballot->update([
            'status' => BallotStatus::Open,
        ]);

        $ballot = $ballot->fresh(['options']);

        BallotOpened::dispatch($ballot);

        return $ballot;
    }
}
