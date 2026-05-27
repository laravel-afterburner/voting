<?php

namespace Afterburner\Voting\Tests\Support;

use Afterburner\Voting\Contracts\ProvidesWeightedVotes;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Support\BallotParticipation;
use Afterburner\Voting\Support\VoterUnit;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Three property units with fixed weights for weighted tally tests.
 */
class TestWeightedVoterEligibilityResolver implements ProvidesWeightedVotes, VoterEligibilityResolver
{
    public const UNIT_TYPE = 'App\\Models\\Property';

    /** @var array<int, float> */
    public const WEIGHTS = [
        1 => 1.0,
        2 => 2.0,
        3 => 0.5,
    ];

    public function eligibleVoterUnits(User $user, Ballot $ballot): Collection
    {
        if (! $user->belongsToTeam($ballot->team) || ! $user->hasPermission('vote_resolutions', $ballot->team_id)) {
            return collect();
        }

        return collect(array_keys(self::WEIGHTS))
            ->map(fn (int $id) => new VoterUnit(self::UNIT_TYPE, $id))
            ->filter(fn (VoterUnit $unit) => $this->canCastVote($user, $ballot, $unit->type, $unit->id)
                || $this->canChangeVote($user, $ballot, $unit->type, $unit->id))
            ->values();
    }

    public function totalEligibleVoterUnits(Ballot $ballot): int
    {
        return count(self::WEIGHTS);
    }

    public function canCastVote(User $user, Ballot $ballot, string $voterUnitType, int $voterUnitId): bool
    {
        if ($voterUnitType !== self::UNIT_TYPE || ! array_key_exists($voterUnitId, self::WEIGHTS)) {
            return false;
        }

        if (! $user->belongsToTeam($ballot->team) || ! $user->hasPermission('vote_resolutions', $ballot->team_id)) {
            return false;
        }

        return $this->unitCanCast($ballot, new VoterUnit($voterUnitType, $voterUnitId));
    }

    public function canChangeVote(User $user, Ballot $ballot, string $voterUnitType, int $voterUnitId): bool
    {
        if (! $ballot->isOpen() || $voterUnitType !== self::UNIT_TYPE || ! array_key_exists($voterUnitId, self::WEIGHTS)) {
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

    public function voterUnitWeight(Ballot $ballot, string $voterUnitType, int $voterUnitId): float
    {
        return self::WEIGHTS[$voterUnitId] ?? 1.0;
    }

    protected function unitCanCast(Ballot $ballot, VoterUnit $unit): bool
    {
        if (BallotParticipation::unitHasRevocation($ballot, $unit->type, $unit->id)) {
            return false;
        }

        return ! BallotParticipation::unitHasResponse($ballot, $unit->type, $unit->id);
    }
}
