<?php

namespace Afterburner\Voting\Livewire\Ballots;

use Afterburner\Voting\Actions\CastVotes;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Support\VoterUnit;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class BulkVoteForm extends Component
{
    use InteractsWithBanner;

    public int $ballotId;

    /** @var array<int, array{type: string, id: int}> */
    public array $units = [];

    public ?int $selectedOptionId = null;

    public function mount(int $ballotId, array $units): void
    {
        $ballot = Ballot::query()->findOrFail($ballotId);

        abort_unless(Auth::user()->can('vote', $ballot), 403);

        $this->authorizeUnits($ballot, $this->normalizeUnits($units));

        $this->ballotId = $ballotId;
        $this->units = $units;

        $this->prefillSelectedOption($ballot);
    }

    public function submitVote(): void
    {
        $this->validate([
            'selectedOptionId' => [
                'required',
                'integer',
                Rule::exists('ballot_options', 'id')->where('ballot_id', $this->ballotId),
            ],
        ]);

        $ballot = Ballot::query()->with('options')->findOrFail($this->ballotId);
        $option = $ballot->options->firstWhere('id', (int) $this->selectedOptionId);
        $units = $this->voterUnits();

        if (! $option) {
            $this->addError('selectedOptionId', __('Please select a valid option.'));

            return;
        }

        $this->authorizeUnits($ballot, $units);

        $hadVotes = BallotResponse::query()
            ->where('ballot_id', $ballot->id)
            ->where('cast_by_user_id', Auth::id())
            ->where(function ($query) use ($units) {
                foreach ($units as $unit) {
                    $query->orWhere(function ($inner) use ($unit) {
                        $inner->where('voter_unit_type', $unit->type)
                            ->where('voter_unit_id', $unit->id);
                    });
                }
            })
            ->exists();

        try {
            app(CastVotes::class)->execute(
                $ballot,
                Auth::user(),
                $option,
                $units,
                request()->ip(),
                request()->userAgent(),
            );

            $this->banner($hadVotes ? __('Your votes have been updated.') : __('Your votes have been recorded.'));
            $this->dispatch('vote-cast')->to(Show::class);
            $this->dispatch('refresh-notifications');
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
            $this->dispatch('vote-cast')->to(Show::class);
        }
    }

    public function render()
    {
        $ballot = Ballot::query()->with('options')->findOrFail($this->ballotId);
        $resolver = app(VoterEligibilityResolver::class);
        $units = $this->voterUnits();

        $unitLabels = $units
            ->map(fn (VoterUnit $unit) => $resolver->voterUnitLabel($unit->type, $unit->id))
            ->all();

        $isChangingVote = $units->contains(
            fn (VoterUnit $unit) => $resolver->canChangeVote(
                Auth::user(),
                $ballot,
                $unit->type,
                $unit->id,
            )
        );

        return view('afterburner-voting::ballots.livewire.bulk-vote-form', [
            'ballot' => $ballot,
            'unitLabels' => $unitLabels,
            'lotCount' => $units->count(),
            'isChangingVote' => $isChangingVote,
        ]);
    }

    /**
     * @return Collection<int, VoterUnit>
     */
    protected function voterUnits(): Collection
    {
        return $this->normalizeUnits($this->units);
    }

    /**
     * @param  array<int, array{type: string, id: int}>  $units
     * @return Collection<int, VoterUnit>
     */
    protected function normalizeUnits(array $units): Collection
    {
        return collect($units)
            ->map(fn (array $unit) => new VoterUnit($unit['type'], (int) $unit['id']))
            ->unique(fn (VoterUnit $unit) => $unit->key())
            ->values();
    }

    protected function prefillSelectedOption(Ballot $ballot): void
    {
        $units = $this->voterUnits();

        $optionIds = BallotResponse::query()
            ->where('ballot_id', $ballot->id)
            ->where('cast_by_user_id', Auth::id())
            ->where(function ($query) use ($units) {
                foreach ($units as $unit) {
                    $query->orWhere(function ($inner) use ($unit) {
                        $inner->where('voter_unit_type', $unit->type)
                            ->where('voter_unit_id', $unit->id);
                    });
                }
            })
            ->pluck('ballot_option_id')
            ->unique()
            ->values();

        if ($optionIds->count() === 1) {
            $this->selectedOptionId = (int) $optionIds->first();
        }
    }

    /**
     * @param  Collection<int, VoterUnit>  $units
     */
    protected function authorizeUnits(Ballot $ballot, Collection $units): void
    {
        if ($units->isEmpty()) {
            abort(403);
        }

        $resolver = app(VoterEligibilityResolver::class);
        $user = Auth::user();

        $authorized = $units->every(
            fn (VoterUnit $unit) => $resolver->canCastVote($user, $ballot, $unit->type, $unit->id)
                || $resolver->canChangeVote($user, $ballot, $unit->type, $unit->id)
        );

        abort_unless($authorized, 403);
    }
}
