<?php

namespace Afterburner\Voting\Http\Controllers;

use Afterburner\Voting\Models\Ballot;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BallotsController
{
    public function index(Team $team): View
    {
        $this->ensureTeamAccess($team);
        abort_unless(Auth::user()->can('viewAny', Ballot::class), 403);

        return view('afterburner-voting::ballots.index', [
            'team' => $team,
        ]);
    }

    public function create(Team $team): View
    {
        $this->ensureTeamAccess($team);

        return view('afterburner-voting::ballots.create', [
            'team' => $team,
        ]);
    }

    public function show(Team $team, Ballot $ballot): View
    {
        $this->ensureTeamAccess($team);
        $this->ensureBallotBelongsToTeam($team, $ballot);

        return view('afterburner-voting::ballots.show', [
            'team' => $team,
            'ballot' => $ballot,
        ]);
    }

    public function edit(Team $team, Ballot $ballot): View
    {
        $this->ensureTeamAccess($team);
        $this->ensureBallotBelongsToTeam($team, $ballot);

        return view('afterburner-voting::ballots.create', [
            'team' => $team,
            'ballot' => $ballot,
        ]);
    }

    public function results(Team $team, Ballot $ballot): View
    {
        $this->ensureTeamAccess($team);
        $this->ensureBallotBelongsToTeam($team, $ballot);

        abort_unless(Auth::user()->can('viewResults', $ballot), 403);

        return view('afterburner-voting::ballots.results', [
            'team' => $team,
            'ballot' => $ballot,
        ]);
    }

    protected function ensureTeamAccess(Team $team): void
    {
        if (! Auth::user()->belongsToTeam($team)) {
            abort(403, 'Access denied.');
        }
    }

    protected function ensureBallotBelongsToTeam(Team $team, Ballot $ballot): void
    {
        if ($ballot->team_id !== $team->id) {
            abort(404);
        }
    }
}
