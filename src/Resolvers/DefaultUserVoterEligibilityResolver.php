<?php

namespace Afterburner\Voting\Resolvers;

use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Models\ProxyVote;
use Afterburner\Voting\Support\BallotParticipation;
use Afterburner\Voting\Support\ElectorateFilter;
use Afterburner\Voting\Support\TeamPermissionGate;
use Afterburner\Voting\Support\TeamVotingSettings;
use Afterburner\Voting\Support\VoterUnit;
use App\Models\User;
use Illuminate\Support\Collection;

class DefaultUserVoterEligibilityResolver implements VoterEligibilityResolver
{
    public function __construct(
        protected ElectorateFilter $electorateFilter,
    ) {}

    public function eligibleVoterUnits(User $user, Ballot $ballot): Collection
    {
        if (! $this->userCanParticipate($user, $ballot)) {
            return collect();
        }

        $units = collect();

        if ($this->electorateFilter->userMeetsElectorate($user, $ballot)) {
            $ownUnit = new VoterUnit(User::class, $user->id);

            if ($this->canChangeVote($user, $ballot, $ownUnit->type, $ownUnit->id)
                || $this->unitIsEligibleToCast($ballot, $ownUnit)) {
                $units->push($ownUnit);
            }
        }

        if (TeamVotingSettings::allowProxyVotesForTeam($ballot->team)) {
            $proxyUnits = ProxyVote::query()
                ->where('ballot_id', $ballot->id)
                ->where('proxy_holder_user_id', $user->id)
                ->active()
                ->get()
                ->map(fn (ProxyVote $proxy) => new VoterUnit(
                    $proxy->grantor_voter_unit_type,
                    $proxy->grantor_voter_unit_id,
                ))
                ->filter(fn (VoterUnit $unit) => $this->canCastVote($user, $ballot, $unit->type, $unit->id));

            $units = $units->merge($proxyUnits);
        }

        return $units->unique(fn (VoterUnit $unit) => $unit->key())->values();
    }

    public function totalEligibleVoterUnits(Ballot $ballot): int
    {
        return $this->electorateFilter->totalEligibleUsers($ballot);
    }

    public function canCastVote(User $user, Ballot $ballot, string $voterUnitType, int $voterUnitId): bool
    {
        if (! $this->userCanParticipate($user, $ballot)) {
            return false;
        }

        if (! $this->unitIsEligibleToCast($ballot, new VoterUnit($voterUnitType, $voterUnitId))) {
            return false;
        }

        if ($voterUnitType === User::class && $voterUnitId === $user->id) {
            return $this->electorateFilter->userMeetsElectorate($user, $ballot);
        }

        if (! TeamVotingSettings::allowProxyVotesForTeam($ballot->team)) {
            return false;
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
        if (! $ballot->isOpen() || ! $this->userCanParticipate($user, $ballot)) {
            return false;
        }

        if (BallotParticipation::unitHasRevocation($ballot, $voterUnitType, $voterUnitId)) {
            return false;
        }

        if ($voterUnitType === User::class && $voterUnitId === $user->id) {
            if (! $this->electorateFilter->userMeetsElectorate($user, $ballot)) {
                return false;
            }

            return BallotResponse::query()
                ->where('ballot_id', $ballot->id)
                ->where('voter_unit_type', User::class)
                ->where('voter_unit_id', $user->id)
                ->where('cast_by_user_id', $user->id)
                ->whereNull('proxy_vote_id')
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
        if ($voterUnitType === User::class) {
            $user = User::query()->find($voterUnitId);

            return $user?->name ?? 'User #'.$voterUnitId;
        }

        return class_basename($voterUnitType).' #'.$voterUnitId;
    }

    protected function userCanParticipate(User $user, Ballot $ballot): bool
    {
        return $user->belongsToTeam($ballot->team)
            && TeamPermissionGate::allows($user, $ballot->team_id, 'vote_resolutions');
    }

    protected function unitIsEligibleToCast(Ballot $ballot, VoterUnit $unit): bool
    {
        if (BallotParticipation::unitHasRevocation($ballot, $unit->type, $unit->id)) {
            return false;
        }

        return ! BallotParticipation::unitHasResponse($ballot, $unit->type, $unit->id);
    }
}
