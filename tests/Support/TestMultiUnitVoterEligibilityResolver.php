<?php

namespace Afterburner\Voting\Tests\Support;

use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Support\BallotParticipation;
use Afterburner\Voting\Support\VoterUnit;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Test double with 40 eligible property units for quorum math tests.
 */
class TestMultiUnitVoterEligibilityResolver implements VoterEligibilityResolver
{
    public const UNIT_TYPE = 'App\\Models\\Property';

    public const UNIT_COUNT = 40;

    public function eligibleVoterUnits(User $user, Ballot $ballot): Collection
    {
        if (! $user->belongsToTeam($ballot->team) || ! $user->hasPermission('vote_resolutions', $ballot->team_id)) {
            return collect();
        }

        return collect(range(1, self::UNIT_COUNT))
            ->map(fn (int $id) => new VoterUnit(self::UNIT_TYPE, $id))
            ->filter(function (VoterUnit $unit) use ($user, $ballot) {
                if ($this->canChangeVote($user, $ballot, $unit->type, $unit->id)) {
                    return true;
                }

                return $this->unitCanCast($ballot, $unit);
            })
            ->values();
    }

    public function totalEligibleVoterUnits(Ballot $ballot): int
    {
        return self::UNIT_COUNT;
    }

    public function canCastVote(User $user, Ballot $ballot, string $voterUnitType, int $voterUnitId): bool
    {
        if ($voterUnitType !== self::UNIT_TYPE || $voterUnitId < 1 || $voterUnitId > self::UNIT_COUNT) {
            return false;
        }

        if (! $this->unitCanCast($ballot, new VoterUnit($voterUnitType, $voterUnitId))) {
            return false;
        }

        return $user->belongsToTeam($ballot->team)
            && $user->hasPermission('vote_resolutions', $ballot->team_id);
    }

    public function canChangeVote(User $user, Ballot $ballot, string $voterUnitType, int $voterUnitId): bool
    {
        if (! $ballot->isOpen() || $voterUnitType !== self::UNIT_TYPE) {
            return false;
        }

        return BallotResponse::query()
            ->where('ballot_id', $ballot->id)
            ->where('voter_unit_type', $voterUnitType)
            ->where('voter_unit_id', $voterUnitId)
            ->where('cast_by_user_id', $user->id)
            ->exists();
    }

    public function voterUnitLabel(string $voterUnitType, int $voterUnitId): string
    {
        return 'Lot '.$voterUnitId;
    }

    protected function unitCanCast(Ballot $ballot, VoterUnit $unit): bool
    {
        if (BallotParticipation::unitHasRevocation($ballot, $unit->type, $unit->id)) {
            return false;
        }

        return ! BallotParticipation::unitHasResponse($ballot, $unit->type, $unit->id);
    }
}
