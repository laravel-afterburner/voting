<?php

namespace Afterburner\Voting\Livewire\Ballots;

use Afterburner\Voting\Actions\CreateBallot;
use Afterburner\Voting\Actions\PublishBallot;
use Afterburner\Voting\Concerns\FlashesNativeBanner;
use Afterburner\Voting\Enums\BallotType;
use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Support\BallotVoteVisibilityGuard;
use Afterburner\Voting\Support\Electorate;
use Afterburner\Voting\Support\ElectorateOptions;
use Afterburner\Voting\Support\TeamDateTime;
use Afterburner\Voting\Support\TeamVotingSettings;
use App\Models\Team;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Create extends Component
{
    use FlashesNativeBanner;
    use InteractsWithBanner;

    public int $teamId;

    public ?int $ballotId = null;

    public string $title = '';

    public string $description = '';

    public string $type = 'resolution';

    /** @var array<int, string> */
    public array $electorate = ['all_members'];

    public string $voteVisibility = 'visible_after_close';

    public bool $confidentialVoting = false;

    public ?string $opensAt = null;

    public ?string $closesAt = null;

    public ?string $quorumPercent = null;

    public bool $allowAbstain = false;

    /** @var array<int, array{label: string, description: string}> */
    public array $options = [
        ['label' => 'Yes', 'description' => ''],
        ['label' => 'No', 'description' => ''],
    ];

    public function mount(Team $team, ?int $ballotId = null): void
    {
        if (! Auth::user()->belongsToTeam($team)) {
            abort(403, 'Access denied.');
        }

        $this->teamId = $team->id;

        if ($ballotId) {
            $ballot = Ballot::query()->where('team_id', $team->id)->findOrFail($ballotId);
            abort_unless(Auth::user()->can('update', $ballot), 403);

            $this->ballotId = $ballot->id;
            $this->title = $ballot->title;
            $this->description = $ballot->description ?? '';
            $this->type = $ballot->type->value;
            $this->electorate = $ballot->electorate->toSelection();
            $this->voteVisibility = $ballot->vote_visibility->value;
            $this->opensAt = TeamDateTime::toDateTimeLocal($team, $ballot->opens_at);
            $this->closesAt = TeamDateTime::toDateTimeLocal($team, $ballot->closes_at);
            $this->quorumPercent = $ballot->quorum_percent !== null ? (string) $ballot->quorum_percent : null;
            $this->allowAbstain = $ballot->allow_abstain;
            $this->options = $ballot->options->map(fn ($option) => [
                'label' => $option->label,
                'description' => $option->description ?? '',
            ])->values()->all();
        } else {
            $this->voteVisibility = TeamVotingSettings::defaultVoteVisibilityForTeam($team)->value;
            $quorum = TeamVotingSettings::defaultQuorumPercentForTeam($team);
            $this->quorumPercent = $quorum !== null ? (string) $quorum : null;
        }

        $this->syncConfidentialFromVisibility();
    }

    public function updatedConfidentialVoting(bool $value): void
    {
        if ($this->isVoteVisibilityLocked()) {
            $this->syncConfidentialFromVisibility();

            return;
        }

        $team = Team::query()->findOrFail($this->teamId);

        $this->voteVisibility = $value
            ? VoteVisibility::Secret->value
            : $this->nonSecretVoteVisibility($team);
    }

    protected function isVoteVisibilityLocked(): bool
    {
        if ($this->ballotId === null) {
            return false;
        }

        $ballot = Ballot::query()->where('team_id', $this->teamId)->find($this->ballotId);

        return $ballot?->voteVisibilityIsLocked() ?? false;
    }

    protected function syncConfidentialFromVisibility(): void
    {
        $this->confidentialVoting = $this->voteVisibility === VoteVisibility::Secret->value;
    }

    protected function nonSecretVoteVisibility(Team $team): string
    {
        $default = TeamVotingSettings::defaultVoteVisibilityForTeam($team);

        if ($default === VoteVisibility::Secret) {
            return VoteVisibility::VisibleAfterClose->value;
        }

        return $default->value;
    }

    public function updatedElectorate(): void
    {
        if ($this->electorate === []) {
            $this->electorate = ['all_members'];

            return;
        }

        if (in_array('all_members', $this->electorate, true) && count($this->electorate) > 1) {
            $this->electorate = array_values(array_filter(
                $this->electorate,
                fn (string $value) => $value !== 'all_members',
            ));
        }
    }

    public function addOption(): void
    {
        $this->options[] = ['label' => '', 'description' => ''];
    }

    public function removeOption(int $index): void
    {
        if (count($this->options) <= 2) {
            return;
        }

        unset($this->options[$index]);
        $this->options = array_values($this->options);
    }

    public function saveDraft(): void
    {
        $wasNew = $this->ballotId === null;
        $ballot = $this->persistBallot();
        $team = Team::query()->findOrFail($this->teamId);

        if ($wasNew) {
            $this->ballotId = $ballot->id;
            $this->banner(__('Ballot draft created. You can attach supporting documents below.'));

            return;
        }

        $this->flashSuccessBanner(__('Ballot draft saved.'));
        $this->redirectRoute('teams.ballots.edit', ['team' => $team, 'ballot' => $ballot]);
    }

    public function saveAndPublish(): void
    {
        $this->validateForPublish();

        $ballot = $this->persistBallot();
        $team = Team::query()->findOrFail($this->teamId);
        $this->ballotId = $ballot->id;

        try {
            app(PublishBallot::class)->execute($ballot->fresh(), Auth::user());
            $this->flashSuccessBanner(__('Ballot published successfully.'));
            $this->redirectRoute('teams.ballots.show', ['team' => $team, 'ballot' => $ballot]);
        } catch (\Throwable $exception) {
            $this->flashDangerBanner($exception->getMessage());
            $this->redirectRoute('teams.ballots.edit', ['team' => $team, 'ballot' => $ballot]);
        }
    }

    protected function validateForPublish(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'type' => 'required|in:poll,resolution,election',
            'electorate' => ['required', 'array', 'min:1'],
            'electorate.*' => ['required', 'string', Rule::in(ElectorateOptions::allowedValues($this->electorate))],
            'voteVisibility' => 'required|in:secret,visible_after_close,visible_realtime',
            'opensAt' => 'required|date',
            'closesAt' => 'required|date|after:opensAt',
            'options' => 'required|array|min:2',
            'options.*.label' => 'required|string|max:255',
        ]);
    }

    protected function persistBallot(): Ballot
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'type' => 'required|in:poll,resolution,election',
            'electorate' => ['required', 'array', 'min:1'],
            'electorate.*' => ['required', 'string', Rule::in(ElectorateOptions::allowedValues($this->electorate))],
            'voteVisibility' => 'required|in:secret,visible_after_close,visible_realtime',
            'options' => 'required|array|min:2',
            'options.*.label' => 'required|string|max:255',
        ]);

        $team = Team::query()->findOrFail($this->teamId);
        $opensAt = TeamDateTime::fromDateTimeLocal($team, $this->opensAt);
        $closesAt = TeamDateTime::fromDateTimeLocal($team, $this->closesAt);
        $options = collect($this->options)
            ->filter(fn ($option) => filled($option['label']))
            ->values()
            ->all();

        if ($this->ballotId) {
            $ballot = Ballot::query()->where('team_id', $this->teamId)->findOrFail($this->ballotId);
            abort_unless(Auth::user()->can('update', $ballot), 403);

            $voteVisibility = BallotVoteVisibilityGuard::resolveForUpdate(
                $ballot,
                VoteVisibility::from($this->voteVisibility),
            );

            $ballot->update([
                'title' => $this->title,
                'description' => $this->description ?: null,
                'type' => BallotType::from($this->type),
                'electorate' => Electorate::fromSelection($this->electorate),
                'vote_visibility' => $voteVisibility,
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
                'quorum_percent' => $this->quorumPercent !== null && $this->quorumPercent !== '' ? $this->quorumPercent : null,
                'allow_abstain' => $this->allowAbstain,
            ]);

            $ballot->options()->delete();
            foreach (array_values($options) as $index => $option) {
                $ballot->options()->create([
                    'label' => $option['label'],
                    'description' => $option['description'] ?: null,
                    'sort_order' => $index,
                ]);
            }

            return $ballot->fresh(['options']);
        }

        return app(CreateBallot::class)->execute(
            $team,
            Auth::user(),
            $this->title,
            $this->description ?: null,
            BallotType::from($this->type),
            Electorate::fromSelection($this->electorate),
            $options,
            VoteVisibility::from($this->voteVisibility),
            $this->quorumPercent !== null && $this->quorumPercent !== '' ? (float) $this->quorumPercent : null,
            $opensAt,
            $closesAt,
            $this->allowAbstain,
        );
    }

    public function render()
    {
        $team = Team::query()->findOrFail($this->teamId);

        $editingBallot = $this->ballotId
            ? Ballot::query()->where('team_id', $this->teamId)->find($this->ballotId)
            : null;

        return view('afterburner-voting::ballots.livewire.create', [
            'team' => $team,
            'isEditing' => $this->ballotId !== null,
            'voteVisibilityLocked' => $editingBallot?->voteVisibilityIsLocked() ?? false,
            'scheduleTimezoneLabel' => TeamDateTime::scheduleTimezoneLabel($team),
            'electorateOptions' => ElectorateOptions::forSelect($this->electorate),
        ]);
    }
}
