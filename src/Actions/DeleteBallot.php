<?php

namespace Afterburner\Voting\Actions;

use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Support\VotingAuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteBallot
{
    public function execute(Ballot $ballot, User $user): void
    {
        Gate::forUser($user)->authorize('delete', $ballot);

        $responsesCount = $ballot->responses()->count();

        VotingAuditLogger::ballotDeleted($ballot, $user, $responsesCount);

        $ballot->delete();
    }
}
