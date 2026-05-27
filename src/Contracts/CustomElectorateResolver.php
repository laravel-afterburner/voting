<?php

namespace Afterburner\Voting\Contracts;

use Afterburner\Voting\Models\Ballot;
use App\Models\User;

interface CustomElectorateResolver
{
    public function userIsEligible(User $user, Ballot $ballot): bool;

    public function totalEligibleVoterUnits(Ballot $ballot): int;
}
