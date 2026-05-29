<?php

namespace Afterburner\Voting\Policies;

use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Services\BallotTallyService;
use Afterburner\Voting\Support\BallotParticipation;
use Afterburner\Voting\Support\SubscriptionEntitlementGate;
use Afterburner\Voting\Support\TeamPermissionGate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BallotPolicy
{
    use HandlesAuthorization;

    public function __construct(
        protected VoterEligibilityResolver $resolver,
        protected BallotTallyService $tallyService,
    ) {}

    public function viewAny(User $user): bool
    {
        if (! $user->currentTeam?->id || ! $user->belongsToTeam($user->currentTeam)) {
            return false;
        }

        return SubscriptionEntitlementGate::allows($user->currentTeam);
    }

    public function view(User $user, Ballot $ballot): bool
    {
        if (! $this->belongsToBallotTeam($user, $ballot)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($ballot->team)) {
            return false;
        }

        return TeamPermissionGate::allowsAny($user, $ballot->team_id, [
            'vote_resolutions',
            'create_resolutions',
            'manage_ballots',
        ]);
    }

    public function create(User $user, Team $team): bool
    {
        if (! $user->belongsToTeam($team)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $team->id, 'create_resolutions');
    }

    public function update(User $user, Ballot $ballot): bool
    {
        if (! $this->belongsToBallotTeam($user, $ballot) || ! $ballot->isEditable()) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($ballot->team)) {
            return false;
        }

        if (TeamPermissionGate::allows($user, $ballot->team_id, 'manage_ballots')) {
            return true;
        }

        return TeamPermissionGate::allows($user, $ballot->team_id, 'create_resolutions')
            && $ballot->created_by_user_id === $user->id;
    }

    public function publish(User $user, Ballot $ballot): bool
    {
        return $this->update($user, $ballot);
    }

    public function close(User $user, Ballot $ballot): bool
    {
        if (! $this->belongsToBallotTeam($user, $ballot) || $ballot->status !== BallotStatus::Open) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($ballot->team)) {
            return false;
        }

        if (TeamPermissionGate::allows($user, $ballot->team_id, 'manage_ballots')) {
            return true;
        }

        return TeamPermissionGate::allows($user, $ballot->team_id, 'create_resolutions')
            && $ballot->created_by_user_id === $user->id;
    }

    public function vote(User $user, Ballot $ballot): bool
    {
        if (! $this->belongsToBallotTeam($user, $ballot) || ! $ballot->isOpen()) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($ballot->team)) {
            return false;
        }

        if (! TeamPermissionGate::allows($user, $ballot->team_id, 'vote_resolutions')) {
            return false;
        }

        return $this->resolver->eligibleVoterUnits($user, $ballot)->isNotEmpty();
    }

    public function viewResults(User $user, Ballot $ballot): bool
    {
        if (! $this->belongsToBallotTeam($user, $ballot)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($ballot->team)) {
            return false;
        }

        $canViewResults = TeamPermissionGate::allowsAny($user, $ballot->team_id, [
            'view_ballot_results',
            'create_resolutions',
            'manage_ballots',
        ]);

        if (! $canViewResults && ! TeamPermissionGate::allows($user, $ballot->team_id, 'vote_resolutions')) {
            return false;
        }

        if ($ballot->status === BallotStatus::Closed) {
            return true;
        }

        if ($ballot->vote_visibility === VoteVisibility::VisibleRealtime && $canViewResults) {
            return true;
        }

        return false;
    }

    public function grantProxy(User $user, Ballot $ballot, string $grantorVoterUnitType, int $grantorVoterUnitId): bool
    {
        return app(ProxyVotePolicy::class)->grant($user, $ballot, $grantorVoterUnitType, $grantorVoterUnitId);
    }

    public function revokeVote(User $user, Ballot $ballot, string $voterUnitType, int $voterUnitId): bool
    {
        if (! config('afterburner-voting.allow_vote_revocation', false)) {
            return false;
        }

        if (! $this->belongsToBallotTeam($user, $ballot) || ! $ballot->isOpen()) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($ballot->team)) {
            return false;
        }

        if (BallotParticipation::unitHasRevocation($ballot, $voterUnitType, $voterUnitId)) {
            return false;
        }

        if (! BallotParticipation::unitHasResponse($ballot, $voterUnitType, $voterUnitId)) {
            return false;
        }

        if (TeamPermissionGate::allows($user, $ballot->team_id, 'manage_ballots')) {
            return true;
        }

        return BallotResponse::query()
            ->where('ballot_id', $ballot->id)
            ->where('voter_unit_type', $voterUnitType)
            ->where('voter_unit_id', $voterUnitId)
            ->where('cast_by_user_id', $user->id)
            ->exists();
    }

    public function exportResults(User $user, Ballot $ballot): bool
    {
        if (! $this->belongsToBallotTeam($user, $ballot)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($ballot->team)) {
            return false;
        }

        return TeamPermissionGate::allows($user, $ballot->team_id, 'export_ballot_results')
            && $this->viewResults($user, $ballot);
    }

    public function delete(User $user, Ballot $ballot): bool
    {
        if (! $this->belongsToBallotTeam($user, $ballot) || $ballot->status !== BallotStatus::Draft) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($ballot->team)) {
            return false;
        }

        if (TeamPermissionGate::allows($user, $ballot->team_id, 'manage_ballots')) {
            return true;
        }

        return TeamPermissionGate::allows($user, $ballot->team_id, 'create_resolutions')
            && $ballot->created_by_user_id === $user->id;
    }

    public function attachDocuments(User $user, Ballot $ballot): bool
    {
        if (! $this->belongsToBallotTeam($user, $ballot)) {
            return false;
        }

        if (! SubscriptionEntitlementGate::allows($ballot->team)) {
            return false;
        }

        if ($ballot->isEditable()) {
            return $this->update($user, $ballot);
        }

        if ($ballot->status === BallotStatus::Open) {
            if (TeamPermissionGate::allows($user, $ballot->team_id, 'manage_ballots')) {
                return true;
            }

            return TeamPermissionGate::allows($user, $ballot->team_id, 'create_resolutions')
                && $ballot->created_by_user_id === $user->id;
        }

        return false;
    }

    protected function belongsToBallotTeam(User $user, Ballot $ballot): bool
    {
        return $user->belongsToTeam($ballot->team)
            && $ballot->team_id === $user->currentTeam?->id;
    }
}
