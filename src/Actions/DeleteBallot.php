<?php

namespace Afterburner\Voting\Actions;

use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\Ballot;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteBallot
{
    public function execute(Ballot $ballot, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $ballot);

        if ($ballot->responses()->exists()) {
            throw new VotingException('Ballots with recorded votes cannot be deleted.');
        }

        $ballot->delete();
    }
}
