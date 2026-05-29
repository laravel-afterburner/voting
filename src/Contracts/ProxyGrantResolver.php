<?php

namespace Afterburner\Voting\Contracts;

use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Support\GrantableVoterUnit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

interface ProxyGrantResolver
{
    public function userCanAccess(User $user, Team $team): bool;

    public function voterUnitType(): string;

    public function voterUnitSelectionLabel(): string;

    /**
     * @return Collection<int, GrantableVoterUnit>
     */
    public function grantableUnitsForUser(User $user, Team $team): Collection;

    public function userCanGrantForUnit(User $user, Team $team, int $unitId): bool;

    /**
     * @return Collection<int, Ballot>
     */
    public function openBallotsForUnit(int $unitId, Team $team, ?Ballot $specificBallot = null): Collection;

    public function grantBlockedReason(User $user, Ballot $ballot, int $unitId): ?string;

    public function unitLabel(int $unitId, Team $team): string;

    public function grantSuccessMessage(int $unitId, Team $team, int $createdCount): string;
}
