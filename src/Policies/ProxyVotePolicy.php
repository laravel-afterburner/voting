<?php

namespace Afterburner\Voting\Policies;

use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\ProxyVote;
use Afterburner\Voting\Support\SubscriptionEntitlementGate;
use Afterburner\Voting\Support\TeamPermissionGate;
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

        if (! SubscriptionEntitlementGate::allows($ballot->team)) {
            return false;
        }

        if (TeamPermissionGate::allows($user, $ballot->team_id, 'manage_proxy_votes')) {
            return true;
        }

        if ($grantorVoterUnitType === User::class && $grantorVoterUnitId === $user->id) {
            return TeamPermissionGate::allows($user, $ballot->team_id, 'vote_resolutions');
        }

        return TeamPermissionGate::allows($user, $ballot->team_id, 'vote_resolutions');
    }

    public function revoke(User $user, ProxyVote $proxy): bool
    {
        if (! $user->belongsToTeam($proxy->team) || $proxy->team_id !== $user->currentTeam?->id) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($proxy->team)) {
            return false;
        }

        if (TeamPermissionGate::allowsAny($user, $proxy->team_id, ['manage_proxy_votes', 'manage_ballots'])) {
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

        if (! SubscriptionEntitlementGate::allows($ballot->team)) {
            return false;
        }

        return $ballot->isOpen()
            && $user->belongsToTeam($ballot->team)
            && TeamPermissionGate::allows($user, $ballot->team_id, 'vote_resolutions');
    }
}
