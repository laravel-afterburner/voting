<?php

namespace Afterburner\Voting\Livewire\Ballots;

use Afterburner\Voting\Actions\CastVote;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Models\ProxyVote;
use Afterburner\Voting\Support\VoterUnit;
use Afterburner\Voting\Support\VoterUnitPartitioner;
use App\Models\User;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class BallotVoteForm extends Component
{
    use InteractsWithBanner;

    public int $ballotId;

    public bool $votePerLot = false;

    public ?int $bulkSelectedOptionId = null;

    /** @var array<string, int|null> */
    public array $selectedOptions = [];

    public function mount(int $ballotId, bool $votePerLot = false): void
    {
        $ballot = Ballot::query()->findOrFail($ballotId);

        abort_unless(Auth::user()->can('vote', $ballot), 403);

        $this->ballotId = $ballotId;
        $this->votePerLot = $votePerLot;

        $this->prefillSelections($ballot);
    }

    public function showVotePerLot(): void
    {
        if ($this->bulkSelectedOptionId !== null) {
            $ballot = Ballot::query()->findOrFail($this->ballotId);
            $ownedLotUnits = $this->partition($ballot, Auth::user())['owned_lot_units'];

            foreach ($ownedLotUnits as $unit) {
                $this->selectedOptions[$unit->key()] = $this->bulkSelectedOptionId;
            }
        }

        $this->votePerLot = true;
    }

    public function showBulkVote(): void
    {
        $ballot = Ballot::query()->findOrFail($this->ballotId);
        $ownedLotUnits = $this->partition($ballot, Auth::user())['owned_lot_units'];

        $optionIds = $ownedLotUnits
            ->map(fn (VoterUnit $unit) => $this->selectedOptions[$unit->key()] ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($optionIds->count() === 1) {
            $this->bulkSelectedOptionId = (int) $optionIds->first();
        }

        $this->votePerLot = false;
    }

    public function submitVote(): void
    {
        $ballot = Ballot::query()->with('options')->findOrFail($this->ballotId);
        $user = Auth::user();
        $resolver = app(VoterEligibilityResolver::class);
        $partition = $this->partition($ballot, $user);
        $supportsBulkLotVoting = app(VoterUnitPartitioner::class)
            ->supportsBulkLotVoting($partition['owned_lot_units']);

        $submissions = collect();

        if ($supportsBulkLotVoting && ! $this->votePerLot) {
            $this->validate([
                'bulkSelectedOptionId' => [
                    'required',
                    'integer',
                    Rule::exists('ballot_options', 'id')->where('ballot_id', $this->ballotId),
                ],
            ]);

            foreach ($partition['owned_lot_units'] as $unit) {
                $submissions->push([
                    'unit' => $unit,
                    'option_id' => (int) $this->bulkSelectedOptionId,
                ]);
            }
        } else {
            foreach ($partition['owned_lot_units'] as $unit) {
                $submissions->push([
                    'unit' => $unit,
                    'option_id' => (int) ($this->selectedOptions[$unit->key()] ?? 0),
                ]);
            }
        }

        foreach ($partition['proxy_units'] as $unit) {
            $submissions->push([
                'unit' => $unit,
                'option_id' => (int) ($this->selectedOptions[$unit->key()] ?? 0),
            ]);
        }

        foreach ($partition['individual_units'] as $unit) {
            $submissions->push([
                'unit' => $unit,
                'option_id' => (int) ($this->selectedOptions[$unit->key()] ?? 0),
            ]);
        }

        if ($submissions->isEmpty()) {
            abort(403);
        }

        $optionRule = [
            'required',
            'integer',
            Rule::exists('ballot_options', 'id')->where('ballot_id', $this->ballotId),
        ];

        $rules = [];

        if ($supportsBulkLotVoting && ! $this->votePerLot) {
            $rules['bulkSelectedOptionId'] = $optionRule;
        }

        foreach ($submissions as $submission) {
            if ($supportsBulkLotVoting && ! $this->votePerLot && $partition['owned_lot_units']->contains(
                fn (VoterUnit $unit) => $unit->key() === $submission['unit']->key()
            )) {
                continue;
            }

            $rules['selectedOptions.'.$submission['unit']->key()] = $optionRule;
        }

        $this->validate($rules);

        foreach ($submissions as $submission) {
            /** @var VoterUnit $unit */
            $unit = $submission['unit'];

            $authorized = $resolver->canCastVote($user, $ballot, $unit->type, $unit->id)
                || $resolver->canChangeVote($user, $ballot, $unit->type, $unit->id);

            if (! $authorized) {
                abort(403);
            }
        }

        $hadVotes = BallotResponse::query()
            ->where('ballot_id', $ballot->id)
            ->where('cast_by_user_id', $user->id)
            ->where(function ($query) use ($submissions) {
                foreach ($submissions as $submission) {
                    /** @var VoterUnit $unit */
                    $unit = $submission['unit'];
                    $query->orWhere(function ($inner) use ($unit) {
                        $inner->where('voter_unit_type', $unit->type)
                            ->where('voter_unit_id', $unit->id);
                    });
                }
            })
            ->exists();

        try {
            DB::transaction(function () use ($ballot, $user, $submissions) {
                foreach ($submissions as $submission) {
                    /** @var VoterUnit $unit */
                    $unit = $submission['unit'];
                    $option = $ballot->options->firstWhere('id', $submission['option_id']);

                    app(CastVote::class)->execute(
                        $ballot,
                        $user,
                        $option,
                        $unit->type,
                        $unit->id,
                        request()->ip(),
                        request()->userAgent(),
                        $this->proxyVoteIdFor($ballot, $unit),
                    );
                }
            });

            $message = $hadVotes
                ? ($submissions->count() > 1 ? __('Your votes have been updated.') : __('Your vote has been updated.'))
                : ($submissions->count() > 1 ? __('Your votes have been recorded.') : __('Your vote has been recorded.'));

            $this->banner($message);
            $this->dispatch('vote-cast');
            $this->dispatch('vote-cast')->to(Show::class);
            $this->dispatch('refresh-notifications');
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
            $this->dispatch('vote-cast');
            $this->dispatch('vote-cast')->to(Show::class);
        }
    }

    public function render()
    {
        $ballot = Ballot::query()->with('options')->findOrFail($this->ballotId);
        $user = Auth::user();
        $resolver = app(VoterEligibilityResolver::class);
        $partition = $this->partition($ballot, $user);
        $ownedLotUnits = $partition['owned_lot_units'];
        $supportsBulkLotVoting = app(VoterUnitPartitioner::class)->supportsBulkLotVoting($ownedLotUnits);

        $ownedLotLabels = $ownedLotUnits
            ->mapWithKeys(fn (VoterUnit $unit) => [
                $unit->key() => $resolver->voterUnitLabel($unit->type, $unit->id),
            ])
            ->all();

        $proxySections = $partition['proxy_units']
            ->map(fn (VoterUnit $unit) => [
                'unit' => $unit,
                'label' => $resolver->voterUnitLabel($unit->type, $unit->id),
                'is_changing' => $resolver->canChangeVote($user, $ballot, $unit->type, $unit->id),
            ])
            ->all();

        $individualSections = $partition['individual_units']
            ->map(fn (VoterUnit $unit) => [
                'unit' => $unit,
                'label' => $resolver->voterUnitLabel($unit->type, $unit->id),
                'is_changing' => $resolver->canChangeVote($user, $ballot, $unit->type, $unit->id),
            ])
            ->all();

        $isChangingVote = $ownedLotUnits->contains(
            fn (VoterUnit $unit) => $resolver->canChangeVote($user, $ballot, $unit->type, $unit->id)
        ) || collect($proxySections)->contains('is_changing', true)
            || collect($individualSections)->contains('is_changing', true);

        return view('afterburner-voting::ballots.livewire.ballot-vote-form', [
            'ballot' => $ballot,
            'ownedLotUnits' => $ownedLotUnits,
            'ownedLotLabels' => $ownedLotLabels,
            'proxySections' => $proxySections,
            'individualSections' => $individualSections,
            'supportsBulkLotVoting' => $supportsBulkLotVoting,
            'isChangingVote' => $isChangingVote,
        ]);
    }

    /**
     * @return array{
     *     owned_lot_units: Collection<int, VoterUnit>,
     *     proxy_units: Collection<int, VoterUnit>,
     *     individual_units: Collection<int, VoterUnit>
     * }
     */
    protected function partition(Ballot $ballot, User $user): array
    {
        $eligibleUnits = app(VoterEligibilityResolver::class)->eligibleVoterUnits($user, $ballot);

        return app(VoterUnitPartitioner::class)->partition($user, $ballot, $eligibleUnits);
    }

    protected function prefillSelections(Ballot $ballot): void
    {
        $user = Auth::user();
        $partition = $this->partition($ballot, $user);
        $ownedLotUnits = $partition['owned_lot_units'];

        foreach ($ownedLotUnits as $unit) {
            $existing = $this->existingResponse($ballot, $unit);

            if ($existing) {
                $this->selectedOptions[$unit->key()] = $existing->ballot_option_id;
            }
        }

        $optionIds = collect($this->selectedOptions)
            ->filter(fn ($optionId, $key) => $ownedLotUnits->contains(fn (VoterUnit $unit) => $unit->key() === $key))
            ->unique()
            ->values();

        if ($optionIds->count() === 1) {
            $this->bulkSelectedOptionId = (int) $optionIds->first();
        }

        foreach ($partition['proxy_units'] as $unit) {
            $existing = $this->existingResponse($ballot, $unit);

            if ($existing) {
                $this->selectedOptions[$unit->key()] = $existing->ballot_option_id;
            }
        }

        foreach ($partition['individual_units'] as $unit) {
            $existing = $this->existingResponse($ballot, $unit);

            if ($existing) {
                $this->selectedOptions[$unit->key()] = $existing->ballot_option_id;
            }
        }
    }

    protected function existingResponse(Ballot $ballot, VoterUnit $unit): ?BallotResponse
    {
        return BallotResponse::query()
            ->where('ballot_id', $ballot->id)
            ->where('voter_unit_type', $unit->type)
            ->where('voter_unit_id', $unit->id)
            ->where('cast_by_user_id', Auth::id())
            ->first();
    }

    protected function proxyVoteIdFor(Ballot $ballot, VoterUnit $unit): ?int
    {
        if ($unit->type === User::class && $unit->id === Auth::id()) {
            return null;
        }

        return ProxyVote::query()
            ->where('ballot_id', $ballot->id)
            ->where('proxy_holder_user_id', Auth::id())
            ->where('grantor_voter_unit_type', $unit->type)
            ->where('grantor_voter_unit_id', $unit->id)
            ->active()
            ->value('id');
    }
}
