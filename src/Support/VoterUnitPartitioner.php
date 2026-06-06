<?php

namespace Afterburner\Voting\Support;

use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\ProxyVote;
use App\Models\User;
use Illuminate\Support\Collection;

class VoterUnitPartitioner
{
    /**
     * @param  Collection<int, VoterUnit>  $eligibleUnits
     * @return array{
     *     owned_lot_units: Collection<int, VoterUnit>,
     *     proxy_units: Collection<int, VoterUnit>,
     *     individual_units: Collection<int, VoterUnit>
     * }
     */
    public function partition(User $user, Ballot $ballot, Collection $eligibleUnits): array
    {
        $ownedLotUnits = collect();
        $proxyUnits = collect();
        $individualUnits = collect();

        foreach ($eligibleUnits as $unit) {
            if ($this->isOwnedLotUnit($user, $ballot, $unit)) {
                $ownedLotUnits->push($unit);

                continue;
            }

            if ($this->isProxyUnit($user, $ballot, $unit)) {
                $proxyUnits->push($unit);

                continue;
            }

            $individualUnits->push($unit);
        }

        return [
            'owned_lot_units' => $ownedLotUnits->values(),
            'proxy_units' => $proxyUnits->values(),
            'individual_units' => $individualUnits->values(),
        ];
    }

    public function supportsBulkLotVoting(Collection $ownedLotUnits): bool
    {
        return $ownedLotUnits->count() >= 2;
    }

    protected function isOwnedLotUnit(User $user, Ballot $ballot, VoterUnit $unit): bool
    {
        if ($unit->type === User::class) {
            return false;
        }

        return ! $this->isProxyUnit($user, $ballot, $unit);
    }

    protected function isProxyUnit(User $user, Ballot $ballot, VoterUnit $unit): bool
    {
        if ($unit->type === User::class && $unit->id === $user->id) {
            return false;
        }

        if (! TeamVotingSettings::allowProxyVotesForTeam($ballot->team)) {
            return false;
        }

        return ProxyVote::query()
            ->where('ballot_id', $ballot->id)
            ->where('proxy_holder_user_id', $user->id)
            ->where('grantor_voter_unit_type', $unit->type)
            ->where('grantor_voter_unit_id', $unit->id)
            ->active()
            ->exists();
    }
}
