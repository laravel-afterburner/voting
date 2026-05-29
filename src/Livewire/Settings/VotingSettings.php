<?php

namespace Afterburner\Voting\Livewire\Settings;

use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Support\TeamVotingSettings;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class VotingSettings extends Component
{
    use InteractsWithBanner;

    public Team $team;

    public ?string $defaultQuorumPercent = null;

    public string $defaultVoteVisibility = 'visible_after_close';

    public bool $allowProxyVotes = true;

    public bool $lockDesignationDuringOpenBallots = false;

    public function mount(Team $team): void
    {
        $this->team = $team;

        Gate::authorize('update', $team);

        $settings = TeamVotingSettings::forTeam($team);

        $this->defaultQuorumPercent = $settings->default_quorum_percent !== null
            ? (string) $settings->default_quorum_percent
            : null;
        $this->defaultVoteVisibility = $settings->default_vote_visibility->value;
        $this->allowProxyVotes = $settings->allow_proxy_votes;
        $this->lockDesignationDuringOpenBallots = $settings->lock_designation_during_open_ballots;
    }

    public function updatedDefaultQuorumPercent(?string $value): void
    {
        Gate::authorize('update', $this->team);

        $validated = $this->validate([
            'defaultQuorumPercent' => 'nullable|numeric|min:0|max:100',
        ]);

        $settings = TeamVotingSettings::forTeam($this->team);
        $settings->update([
            'default_quorum_percent' => filled($validated['defaultQuorumPercent'])
                ? (float) $validated['defaultQuorumPercent']
                : null,
        ]);

        $this->banner(__('Default quorum updated.'));
    }

    public function updatedDefaultVoteVisibility(string $value): void
    {
        Gate::authorize('update', $this->team);

        $this->validate([
            'defaultVoteVisibility' => 'required|in:secret,visible_after_close,visible_realtime',
        ]);

        $settings = TeamVotingSettings::forTeam($this->team);
        $settings->update([
            'default_vote_visibility' => VoteVisibility::from($value),
        ]);

        $this->banner(__('Default vote visibility updated.'));
    }

    public function updatedAllowProxyVotes(bool $value): void
    {
        Gate::authorize('update', $this->team);

        $settings = TeamVotingSettings::forTeam($this->team);
        $settings->update(['allow_proxy_votes' => $value]);

        $this->banner($value
            ? __('Proxy votes enabled for this team.')
            : __('Proxy votes disabled for this team.'));

        $this->dispatch('refresh-navigation-menu');
    }

    public function updatedLockDesignationDuringOpenBallots(bool $value): void
    {
        Gate::authorize('update', $this->team);

        $settings = TeamVotingSettings::forTeam($this->team);
        $settings->update(['lock_designation_during_open_ballots' => $value]);

        $this->banner($value
            ? __('Designated voter changes will be locked while ballots are open.')
            : __('Designated voter changes are allowed during open ballots.'));
    }

    public function render()
    {
        return view('afterburner-voting::settings.livewire.voting-settings', [
            'visibilityOptions' => VoteVisibility::cases(),
        ]);
    }
}
