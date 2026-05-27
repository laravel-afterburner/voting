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
 * Test double for strata-style voting: one vote per property unit, any team member may cast.
 */
class TestPropertyVoterEligibilityResolver implements VoterEligibilityResolver
{
    public const UNIT_TYPE = 'App\\Models\\Property';

    public function eligibleVoterUnits(User $user, Ballot $ballot): Collection
    {
        if (! $user->belongsToTeam($ballot->team) || ! $user->hasPermission('vote_resolutions', $ballot->team_id)) {
            return collect();
        }

        $units = collect();
        $unit = new VoterUnit(self::UNIT_TYPE, 1);

        if ($this->canChangeVote($user, $ballot, $unit->type, $unit->id) || $this->unitCanCast($ballot, $unit)) {
            $units->push($unit);
        }

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

        return $units->unique(fn (VoterUnit $u) => $u->key())->values();
    }

    public function totalEligibleVoterUnits(Ballot $ballot): int
    {
        return 1;
    }

    public function canCastVote(User $user, Ballot $ballot, string $voterUnitType, int $voterUnitId): bool
    {
        if ($voterUnitType !== self::UNIT_TYPE || $voterUnitId !== 1) {
            return false;
        }

        if (! $this->unitCanCast($ballot, new VoterUnit($voterUnitType, $voterUnitId))) {
            return false;
        }

        if ($user->belongsToTeam($ballot->team) && $user->hasPermission('vote_resolutions', $ballot->team_id)) {
            return true;
        }

        return ProxyVote::query()
            ->where('ballot_id', $ballot->id)
            ->where('proxy_holder_user_id', $user->id)
            ->where('grantor_voter_unit_type', $voterUnitType)
            ->where('grantor_voter_unit_id', $voterUnitId)
            ->active()
            ->exists();
    }

    public function canChangeVote(User $user, Ballot $ballot, string $voterUnitType, int $voterUnitId): bool
    {
        if (! $ballot->isOpen() || $voterUnitType !== self::UNIT_TYPE || $voterUnitId !== 1) {
            return false;
        }

        if (! $user->belongsToTeam($ballot->team) || ! $user->hasPermission('vote_resolutions', $ballot->team_id)) {
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
