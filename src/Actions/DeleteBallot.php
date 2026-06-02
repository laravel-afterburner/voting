<?php

namespace Afterburner\Voting\Actions;

use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Support\VotingAuditLogger;
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

        VotingAuditLogger::ballotDeleted($ballot, $user);

        $ballot->delete();
    }
}
