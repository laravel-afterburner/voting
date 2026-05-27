<?php

namespace Afterburner\Voting\Http\Controllers;

use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VotingSettingsController
{
    /**
     * Display team voting settings.
     */
    public function __invoke(Team $team): View
    {
        if (! Auth::user()->belongsToTeam($team)) {
            abort(403, 'Access denied.');
        }

        Gate::authorize('update', $team);

        return view('afterburner-voting::settings.voting-settings', [
            'team' => $team,
        ]);
    }
}
