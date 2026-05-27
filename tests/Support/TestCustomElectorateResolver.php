<?php

namespace Afterburner\Voting\Tests\Support;

use Afterburner\Voting\Contracts\CustomElectorateResolver;
use Afterburner\Voting\Models\Ballot;
use App\Models\User;

class TestCustomElectorateResolver implements CustomElectorateResolver
{
    public function userIsEligible(User $user, Ballot $ballot): bool
    {
        return true;
    }

    public function totalEligibleVoterUnits(Ballot $ballot): int
    {
        return 1;
    }
}
