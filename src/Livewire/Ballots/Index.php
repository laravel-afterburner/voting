<?php

namespace Afterburner\Voting\Livewire\Ballots;

use Afterburner\Voting\Support\BallotParticipation;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Models\Ballot;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public int $teamId;

    public function mount(Team $team): void
    {
        if (! Auth::user()->belongsToTeam($team)) {
            abort(403, 'Access denied.');
        }

        abort_unless(Auth::user()->can('viewAny', Ballot::class), 403);

        $this->teamId = $team->id;
    }

    public function getActionRequiredCountProperty(): int
    {
        $resolver = app(VoterEligibilityResolver::class);
        $user = Auth::user();

        return Ballot::query()
            ->forTeam($this->teamId)
            ->where('status', BallotStatus::Open)
            ->get()
            ->filter(fn (Ballot $ballot) => BallotParticipation::userHasPendingVote($user, $ballot, $resolver))
            ->count();
    }

    public function render()
    {
        $team = Team::query()->findOrFail($this->teamId);

        $ballots = Ballot::query()
            ->forTeam($this->teamId)
            ->with('creator')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('afterburner-voting::ballots.livewire.index', [
            'team' => $team,
            'ballots' => $ballots,
            'actionRequiredCount' => $this->actionRequiredCount,
            'canCreate' => Auth::user()->can('create', [Ballot::class, $team]),
        ]);
    }
}
