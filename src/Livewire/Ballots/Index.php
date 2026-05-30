<?php

namespace Afterburner\Voting\Livewire\Ballots;

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

    public string $tab = 'open';

    protected $queryString = [
        'tab' => ['except' => 'open'],
    ];

    public function mount(Team $team): void
    {
        if (! Auth::user()->belongsToTeam($team)) {
            abort(403, 'Access denied.');
        }

        abort_unless(Auth::user()->can('viewAny', Ballot::class), 403);

        $this->teamId = $team->id;
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['open', 'upcoming', 'closed'], true)) {
            return;
        }

        $this->tab = $tab;
        $this->resetPage();
    }

    public function createBallot()
    {
        return $this->redirectRoute('teams.ballots.create', ['team' => $this->teamId]);
    }

    public function viewBallot(int $ballotId)
    {
        return $this->redirectRoute('teams.ballots.show', ['team' => $this->teamId, 'ballot' => $ballotId]);
    }

    public function editBallot(int $ballotId)
    {
        return $this->redirectRoute('teams.ballots.edit', ['team' => $this->teamId, 'ballot' => $ballotId]);
    }

    public function getActionRequiredCountProperty(): int
    {
        $resolver = app(VoterEligibilityResolver::class);
        $user = Auth::user();

        return Ballot::query()
            ->forTeam($this->teamId)
            ->where('status', BallotStatus::Open)
            ->get()
            ->filter(fn (Ballot $ballot) => $resolver->eligibleVoterUnits($user, $ballot)->isNotEmpty())
            ->count();
    }

    public function render()
    {
        $team = Team::query()->findOrFail($this->teamId);
        $query = Ballot::query()->forTeam($this->teamId)->with('creator');

        $query = match ($this->tab) {
            'open' => $query->where('status', BallotStatus::Open),
            'upcoming' => $query->whereIn('status', [BallotStatus::Draft, BallotStatus::Scheduled]),
            'closed' => $query->whereIn('status', [BallotStatus::Closed, BallotStatus::Cancelled]),
            default => $query,
        };

        $ballots = $query->orderByDesc('published_at')->orderByDesc('created_at')->paginate(15);

        return view('afterburner-voting::ballots.livewire.index', [
            'team' => $team,
            'ballots' => $ballots,
            'actionRequiredCount' => $this->actionRequiredCount,
            'canCreate' => Auth::user()->can('create', [Ballot::class, $team]),
        ]);
    }
}
