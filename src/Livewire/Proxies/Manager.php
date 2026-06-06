<?php

namespace Afterburner\Voting\Livewire\Proxies;

use App\Support\EntityLabel;
use Afterburner\Voting\Actions\CreateProxy;
use Afterburner\Voting\Actions\RevokeProxy;
use Afterburner\Voting\Contracts\ProxyGrantResolver;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\ProxyVote;
use Afterburner\Voting\Support\TeamVotingSettings;
use App\Models\Team;
use App\Models\User;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Manager extends Component
{
    use InteractsWithBanner;
    use WithPagination;

    public Team $team;

    public ?int $voterUnitId = null;

    public ?int $proxyHolderUserId = null;

    public string $ballotScope = 'single';

    public ?int $ballotId = null;

    public ?string $validUntil = null;

    protected ProxyGrantResolver $resolver;

    public function boot(ProxyGrantResolver $resolver): void
    {
        $this->resolver = $resolver;
    }

    public function mount(Team $team): void
    {
        abort_unless($team->id === Auth::user()?->currentTeam?->id, 403);
        abort_unless($this->resolver->userCanAccess(Auth::user(), $team), 403);

        $this->team = $team;
    }

    public function grantProxy(): void
    {
        $user = Auth::user();

        abort_unless($this->resolver->userCanAccess($user, $this->team), 403);
        abort_unless(TeamVotingSettings::allowProxyVotesForTeam($this->team), 403);

        $this->validate([
            'voterUnitId' => ['required', 'integer'],
            'proxyHolderUserId' => ['required', 'integer', 'exists:users,id'],
            'ballotScope' => ['required', 'in:single,all_open'],
            'ballotId' => ['nullable', 'integer', 'exists:ballots,id'],
            'validUntil' => ['nullable', 'date', 'after:now'],
        ]);

        abort_unless($this->resolver->userCanGrantForUnit($user, $this->team, $this->voterUnitId), 403);

        $proxyHolder = User::query()->findOrFail($this->proxyHolderUserId);

        if (! $proxyHolder->belongsToTeam($this->team)) {
            $this->addError('proxyHolderUserId', 'Proxy holder must be a '.EntityLabel::singular().' member.');

            return;
        }

        if ($proxyHolder->id === $user->id) {
            $this->addError('proxyHolderUserId', 'You cannot assign yourself as the proxy holder.');

            return;
        }

        $ballots = $this->resolveTargetBallots();

        if ($ballots->isEmpty()) {
            $this->addError('ballotId', 'No eligible open ballots were found for this '.$this->resolver->voterUnitSelectionLabel().'.');

            return;
        }

        $validUntil = $this->validUntil ? now()->parse($this->validUntil) : null;
        $created = 0;
        $skipped = [];

        foreach ($ballots as $ballot) {
            $blockedReason = $this->resolver->grantBlockedReason($user, $ballot, $this->voterUnitId);

            if ($blockedReason !== null) {
                $skipped[] = $ballot->title.': '.$blockedReason;

                continue;
            }

            try {
                app(CreateProxy::class)->execute(
                    $ballot,
                    $user,
                    $proxyHolder,
                    $this->resolver->voterUnitType(),
                    $this->voterUnitId,
                    null,
                    $validUntil,
                );
                $created++;
            } catch (VotingException $exception) {
                $skipped[] = $ballot->title.': '.$exception->getMessage();
            }
        }

        if ($created === 0) {
            $this->dangerBanner($skipped[0] ?? 'Unable to grant proxy.');

            return;
        }

        $message = $this->resolver->grantSuccessMessage($this->voterUnitId, $this->team, $created);

        if ($skipped !== []) {
            $message .= ' Some ballots were skipped.';
        }

        $this->banner($message);

        $this->reset(['voterUnitId', 'proxyHolderUserId', 'ballotId', 'validUntil']);
        $this->ballotScope = 'single';
    }

    public function revokeProxy(int $proxyId): void
    {
        $user = Auth::user();
        $proxy = ProxyVote::query()
            ->where('team_id', $this->team->id)
            ->whereKey($proxyId)
            ->firstOrFail();

        abort_unless($user->can('revoke', $proxy), 403);

        try {
            app(RevokeProxy::class)->execute($proxy, $user);
        } catch (VotingException $exception) {
            $this->dangerBanner($exception->getMessage());

            return;
        }

        $this->banner('Proxy revoked.');
    }

    protected function resolveTargetBallots(): Collection
    {
        if ($this->voterUnitId === null) {
            return collect();
        }

        if ($this->ballotScope === 'all_open') {
            return $this->resolver->openBallotsForUnit($this->voterUnitId, $this->team);
        }

        if ($this->ballotId === null) {
            return collect();
        }

        $ballot = Ballot::query()
            ->where('team_id', $this->team->id)
            ->whereKey($this->ballotId)
            ->first();

        if (! $ballot) {
            return collect();
        }

        return $this->resolver->openBallotsForUnit($this->voterUnitId, $this->team, $ballot);
    }

    public function render()
    {
        $user = Auth::user();
        $canManageAll = $user->hasPermission('manage_proxy_votes', $this->team->id);
        $proxiesEnabled = TeamVotingSettings::allowProxyVotesForTeam($this->team);
        $grantableUnits = $this->resolver->grantableUnitsForUser($user, $this->team);

        $eligibleBallots = $this->voterUnitId
            ? $this->resolver->openBallotsForUnit($this->voterUnitId, $this->team)
            : collect();

        $teamMembers = User::query()
            ->whereHas('teams', fn ($query) => $query->where('teams.id', $this->team->id))
            ->where('users.id', '!=', $user->id)
            ->orderBy('name')
            ->get();

        $proxiesQuery = ProxyVote::query()
            ->where('team_id', $this->team->id)
            ->where('grantor_voter_unit_type', $this->resolver->voterUnitType())
            ->with(['ballot', 'proxyHolder', 'grantedBy'])
            ->latest('created_at');

        if (! $canManageAll) {
            $grantableUnitIds = $grantableUnits->pluck('id');

            $proxiesQuery->where(function ($query) use ($user, $grantableUnitIds) {
                $query->whereIn('grantor_voter_unit_id', $grantableUnitIds)
                    ->orWhere('proxy_holder_user_id', $user->id)
                    ->orWhere('granted_by_user_id', $user->id);
            });
        }

        $proxies = $proxiesQuery->paginate(15);
        $unitLabelsById = $proxies
            ->pluck('grantor_voter_unit_id')
            ->unique()
            ->mapWithKeys(fn (int $unitId) => [
                $unitId => $this->resolver->unitLabel($unitId, $this->team),
            ]);

        return view('afterburner-voting::proxies.livewire.manager', [
            'proxiesEnabled' => $proxiesEnabled,
            'canManageAll' => $canManageAll,
            'grantableUnits' => $grantableUnits,
            'eligibleBallots' => $eligibleBallots,
            'teamMembers' => $teamMembers,
            'proxies' => $proxies,
            'unitLabelsById' => $unitLabelsById,
            'voterUnitSelectionLabel' => $this->resolver->voterUnitSelectionLabel(),
        ]);
    }
}
