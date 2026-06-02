<?php

namespace Afterburner\Voting\Actions;

use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\ProxyVote;
use Afterburner\Voting\Support\TeamVotingSettings;
use Afterburner\Voting\Support\VotingAuditLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateProxy
{
    public function execute(
        Ballot $ballot,
        User $grantedBy,
        User $proxyHolder,
        string $grantorVoterUnitType,
        int $grantorVoterUnitId,
        ?\DateTimeInterface $validFrom = null,
        ?\DateTimeInterface $validUntil = null,
    ): ProxyVote {
        if (! TeamVotingSettings::allowProxyVotesForTeam($ballot->team)) {
            throw new VotingException('Proxy votes are not enabled.');
        }

        Gate::forUser($grantedBy)->authorize('grantProxy', [
            $ballot,
            $grantorVoterUnitType,
            $grantorVoterUnitId,
        ]);

        if ($proxyHolder->id === $grantedBy->id && $grantorVoterUnitType === User::class && $grantorVoterUnitId === $grantedBy->id) {
            throw new VotingException('You cannot assign a proxy to yourself for your own vote.');
        }

        if (! $proxyHolder->belongsToTeam($ballot->team)) {
            throw new VotingException('Proxy holder must belong to this team.');
        }

        $existing = ProxyVote::query()
            ->where('ballot_id', $ballot->id)
            ->where('grantor_voter_unit_type', $grantorVoterUnitType)
            ->where('grantor_voter_unit_id', $grantorVoterUnitId)
            ->active()
            ->exists();

        if ($existing) {
            throw new VotingException('An active proxy already exists for this voting unit.');
        }

        return DB::transaction(function () use ($ballot, $grantedBy, $proxyHolder, $grantorVoterUnitType, $grantorVoterUnitId, $validFrom, $validUntil) {
            $proxy = ProxyVote::query()->create([
                'team_id' => $ballot->team_id,
                'ballot_id' => $ballot->id,
                'grantor_voter_unit_type' => $grantorVoterUnitType,
                'grantor_voter_unit_id' => $grantorVoterUnitId,
                'proxy_holder_user_id' => $proxyHolder->id,
                'granted_by_user_id' => $grantedBy->id,
                'valid_from' => $validFrom ?? now(),
                'valid_until' => $validUntil,
            ]);

            VotingAuditLogger::proxyCreated($proxy, $grantedBy);

            return $proxy;
        });
    }
}
