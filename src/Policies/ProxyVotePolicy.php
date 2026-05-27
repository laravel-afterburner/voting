<?php

namespace Afterburner\Voting\Policies;

use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\ProxyVote;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProxyVotePolicy
{
    use HandlesAuthorization;

    public function grant(User $user, Ballot $ballot, string $grantorVoterUnitType, int $grantorVoterUnitId): bool
    {
        if (! $user->belongsToTeam($ballot->team) || $ballot->team_id !== $user->currentTeam?->id) {
            return false;
        }

        if ($user->hasPermission('manage_proxy_votes', $ballot->team_id)) {
            return true;
        }

        if ($grantorVoterUnitType === User::class && $grantorVoterUnitId === $user->id) {
            return $user->hasPermission('vote_resolutions', $ballot->team_id);
        }

        return $user->hasPermission('vote_resolutions', $ballot->team_id);
    }

    public function revoke(User $user, ProxyVote $proxy): bool
    {
        if (! $user->belongsToTeam($proxy->team) || $proxy->team_id !== $user->currentTeam?->id) {
            return false;
        }

        if ($user->hasPermission('manage_proxy_votes', $proxy->team_id)
            || $user->hasPermission('manage_ballots', $proxy->team_id)) {
            return true;
        }

        return $proxy->granted_by_user_id === $user->id
            || $proxy->proxy_holder_user_id === $user->id
            || ($proxy->grantor_voter_unit_type === User::class && $proxy->grantor_voter_unit_id === $user->id);
    }

    public function exercise(User $user, ProxyVote $proxy): bool
    {
        if (! $proxy->isActive() || $proxy->proxy_holder_user_id !== $user->id) {
            return false;
        }

        $ballot = $proxy->ballot;

        return $ballot->isOpen()
            && $user->belongsToTeam($ballot->team)
            && $user->hasPermission('vote_resolutions', $ballot->team_id);
    }
}
