<?php

namespace Afterburner\Voting\Contracts;

use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Support\VoterUnit;
use App\Models\User;
use Illuminate\Support\Collection;

interface VoterEligibilityResolver
{
    /**
     * @return Collection<int, VoterUnit>
     */
    public function eligibleVoterUnits(User $user, Ballot $ballot): Collection;

    public function totalEligibleVoterUnits(Ballot $ballot): int;

    public function canCastVote(User $user, Ballot $ballot, string $voterUnitType, int $voterUnitId): bool;

    public function canChangeVote(User $user, Ballot $ballot, string $voterUnitType, int $voterUnitId): bool;

    public function voterUnitLabel(string $voterUnitType, int $voterUnitId): string;
}
