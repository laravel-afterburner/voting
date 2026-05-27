<?php

namespace Afterburner\Voting\Livewire\Ballots;

use Afterburner\Voting\Actions\CastVote;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Models\ProxyVote;
use Afterburner\Voting\Support\VoterUnit;
use App\Models\User;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class VoteForm extends Component
{
    use InteractsWithBanner;

    public int $ballotId;

    public string $voterUnitType = '';

    public int $voterUnitId = 0;

    public ?int $selectedOptionId = null;

    public function mount(int $ballotId, string $voterUnitType, int $voterUnitId): void
    {
        $ballot = Ballot::query()->findOrFail($ballotId);

        abort_unless(Auth::user()->can('vote', $ballot), 403);

        $user = Auth::user();
        $resolver = app(VoterEligibilityResolver::class);

        $canCast = $resolver->canCastVote($user, $ballot, $voterUnitType, $voterUnitId);
        $canChange = $resolver->canChangeVote($user, $ballot, $voterUnitType, $voterUnitId);

        abort_unless($canCast || $canChange, 403);

        $existing = BallotResponse::query()
            ->where('ballot_id', $ballot->id)
            ->where('voter_unit_type', $voterUnitType)
            ->where('voter_unit_id', $voterUnitId)
            ->first();

        if ($existing) {
            $this->selectedOptionId = $existing->ballot_option_id;
        }

        $this->ballotId = $ballotId;
        $this->voterUnitType = $voterUnitType;
        $this->voterUnitId = $voterUnitId;
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

        if (! $option) {
            $this->addError('selectedOptionId', __('Please select a valid option.'));

            return;
        }

        $hadVote = BallotResponse::query()
            ->where('ballot_id', $ballot->id)
            ->where('voter_unit_type', $this->voterUnitType)
            ->where('voter_unit_id', $this->voterUnitId)
            ->where('cast_by_user_id', Auth::id())
            ->exists();

        $proxyVoteId = null;
        if ($this->voterUnitType !== User::class || $this->voterUnitId !== Auth::id()) {
            $proxyVoteId = ProxyVote::query()
                ->where('ballot_id', $ballot->id)
                ->where('proxy_holder_user_id', Auth::id())
                ->where('grantor_voter_unit_type', $this->voterUnitType)
                ->where('grantor_voter_unit_id', $this->voterUnitId)
                ->active()
                ->value('id');
        }

        try {
            app(CastVote::class)->execute(
                $ballot,
                Auth::user(),
                $option,
                $this->voterUnitType,
                $this->voterUnitId,
                request()->ip(),
                request()->userAgent(),
                $proxyVoteId,
            );

            $this->banner($hadVote ? __('Your vote has been updated.') : __('Your vote has been recorded.'));
            $this->dispatch('vote-cast')->to(Show::class);
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
            $this->dispatch('vote-cast')->to(Show::class);
        }
    }

    public function render()
    {
        $ballot = Ballot::query()->with('options')->findOrFail($this->ballotId);
        $resolver = app(VoterEligibilityResolver::class);
        $unit = new VoterUnit($this->voterUnitType, $this->voterUnitId);

        $canChange = $resolver->canChangeVote(Auth::user(), $ballot, $this->voterUnitType, $this->voterUnitId);

        $proxy = ProxyVote::query()
            ->where('ballot_id', $ballot->id)
            ->where('proxy_holder_user_id', Auth::id())
            ->where('grantor_voter_unit_type', $this->voterUnitType)
            ->where('grantor_voter_unit_id', $this->voterUnitId)
            ->active()
            ->first();

        return view('afterburner-voting::ballots.livewire.vote-form', [
            'ballot' => $ballot,
            'unitLabel' => $resolver->voterUnitLabel($unit->type, $unit->id),
            'isChangingVote' => $canChange,
            'isProxyVote' => $proxy !== null,
        ]);
    }
}
