<?php

namespace Afterburner\Voting\Livewire\Settings;

use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Support\TeamVotingSettings;
use Afterburner\Voting\Support\VotingAuditLogger;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class VotingSettings extends Component
{
    public Team $team;

    public ?string $defaultQuorumPercent = null;

    public string $defaultVoteVisibility = 'secret';

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

    public function save(): void
    {
        Gate::authorize('update', $this->team);

        $validated = $this->validate([
            'defaultQuorumPercent' => 'nullable|numeric|min:0|max:100',
            'defaultVoteVisibility' => 'required|in:secret,visible_after_close,visible_realtime',
            'allowProxyVotes' => 'boolean',
            'lockDesignationDuringOpenBallots' => 'boolean',
        ]);

        $settings = TeamVotingSettings::forTeam($this->team);
        $previousAllowProxyVotes = $settings->allow_proxy_votes;

        $settings->update([
            'default_quorum_percent' => filled($validated['defaultQuorumPercent'])
                ? (float) $validated['defaultQuorumPercent']
                : null,
            'default_vote_visibility' => VoteVisibility::from($validated['defaultVoteVisibility']),
            'allow_proxy_votes' => $validated['allowProxyVotes'],
            'lock_designation_during_open_ballots' => $validated['lockDesignationDuringOpenBallots'],
        ]);

        if ($previousAllowProxyVotes !== $validated['allowProxyVotes']) {
            $this->dispatch('refresh-navigation-menu');
        }

        VotingAuditLogger::settingsUpdated($this->team, Auth::user(), [
            'default_quorum_percent' => $validated['defaultQuorumPercent'] ?? null,
            'default_vote_visibility' => $validated['defaultVoteVisibility'],
            'allow_proxy_votes' => $validated['allowProxyVotes'],
            'lock_designation_during_open_ballots' => $validated['lockDesignationDuringOpenBallots'],
        ]);

        $this->dispatch('saved');
    }

    public function render()
    {
        return view('afterburner-voting::settings.livewire.voting-settings', [
            'visibilityOptions' => VoteVisibility::cases(),
        ]);
    }
}
