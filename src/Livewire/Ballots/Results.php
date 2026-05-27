<?php

namespace Afterburner\Voting\Livewire\Ballots;

use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Services\BallotTallyService;
use Afterburner\Voting\Services\QuorumService;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Results extends Component
{
    public int $teamId;

    public int $ballotId;

    public function mount(Team $team, Ballot $ballot): void
    {
        if ($ballot->team_id !== $team->id) {
            abort(404);
        }

        abort_unless(Auth::user()->can('viewResults', $ballot), 403);

        $this->teamId = $team->id;
        $this->ballotId = $ballot->id;
    }

    public function exportResults(string $format = 'csv')
    {
        return $this->redirectRoute('teams.ballots.results.export', [
            'team' => $this->teamId,
            'ballot' => $this->ballotId,
            'format' => $format === 'pdf' ? 'pdf' : null,
        ]);
    }

    public function render()
    {
        $ballot = Ballot::query()
            ->with('options')
            ->where('team_id', $this->teamId)
            ->findOrFail($this->ballotId);

        $tallyService = app(BallotTallyService::class);
        $canViewTally = $tallyService->canViewTally($ballot);

        return view('afterburner-voting::ballots.livewire.results', [
            'team' => Team::query()->findOrFail($this->teamId),
            'ballot' => $ballot,
            'tally' => $canViewTally ? $tallyService->tally($ballot) : null,
            'quorum' => app(QuorumService::class)->calculate($ballot),
            'responseDetails' => $canViewTally ? $tallyService->responseDetails($ballot) : [],
            'canViewTally' => $canViewTally,
            'canExport' => Auth::user()->can('exportResults', $ballot),
        ]);
    }
}
