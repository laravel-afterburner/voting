<?php

namespace Afterburner\Voting\Tests\Support;

use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Models\ProxyVote;
use Afterburner\Voting\Support\BallotParticipation;
use Afterburner\Voting\Support\VoterUnit;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Test double: one user may represent lots 1–3 as owner; proxies handled separately.
 */
class TestMultiLotOwnerVoterEligibilityResolver implements VoterEligibilityResolver
{
    public const UNIT_TYPE = 'App\\Models\\Property';

    /** @var array<int, int> */
    public const OWNED_LOT_IDS = [1, 2, 3];

    public function eligibleVoterUnits(User $user, Ballot $ballot): Collection
    {
        if (! $user->belongsToTeam($ballot->team) || ! $user->hasPermission('vote_resolutions', $ballot->team_id)) {
            return collect();
        }

        $units = collect(self::OWNED_LOT_IDS)
            ->map(fn (int $id) => new VoterUnit(self::UNIT_TYPE, $id))
            ->filter(fn (VoterUnit $unit) => $this->canChangeVote($user, $ballot, $unit->type, $unit->id)
                || $this->unitCanCast($ballot, $unit));

        if (config('afterburner-voting.allow_proxy_votes', true)) {
            $proxyUnits = ProxyVote::query()
                ->where('ballot_id', $ballot->id)
                ->where('proxy_holder_user_id', $user->id)
                ->active()
                ->get()
                ->map(fn (ProxyVote $proxy) => new VoterUnit(
                    $proxy->grantor_voter_unit_type,
                    $proxy->grantor_voter_unit_id,
                ))
                ->filter(fn (VoterUnit $proxyUnit) => $this->unitCanCast($ballot, $proxyUnit));

            $units = $units->merge($proxyUnits);
        }

        return $units->unique(fn (VoterUnit $unit) => $unit->key())->values();
    }

    public function totalEligibleVoterUnits(Ballot $ballot): int
    {
        return count(self::OWNED_LOT_IDS);
    }

    public function canCastVote(User $user, Ballot $ballot, string $voterUnitType, int $voterUnitId): bool
    {
        if (! $user->belongsToTeam($ballot->team) || ! $user->hasPermission('vote_resolutions', $ballot->team_id)) {
            return false;
        }

        if ($voterUnitType === self::UNIT_TYPE && in_array($voterUnitId, self::OWNED_LOT_IDS, true)) {
            return $this->unitCanCast($ballot, new VoterUnit($voterUnitType, $voterUnitId));
        }

        return ProxyVote::query()
            ->where('ballot_id', $ballot->id)
            ->where('proxy_holder_user_id', $user->id)
            ->where('grantor_voter_unit_type', $voterUnitType)
            ->where('grantor_voter_unit_id', $voterUnitId)
            ->active()
            ->exists() && $this->unitCanCast($ballot, new VoterUnit($voterUnitType, $voterUnitId));
    }

    public function canChangeVote(User $user, Ballot $ballot, string $voterUnitType, int $voterUnitId): bool
    {
        if (! $ballot->isOpen()) {
            return false;
        }

        if ($voterUnitType === self::UNIT_TYPE && in_array($voterUnitId, self::OWNED_LOT_IDS, true)) {
            return BallotResponse::query()
                ->where('ballot_id', $ballot->id)
                ->where('voter_unit_type', $voterUnitType)
                ->where('voter_unit_id', $voterUnitId)
                ->where('cast_by_user_id', $user->id)
                ->exists();
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
