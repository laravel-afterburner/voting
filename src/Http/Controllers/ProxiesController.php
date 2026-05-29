<?php

namespace Afterburner\Voting\Http\Controllers;

use Afterburner\Voting\Contracts\ProxyGrantResolver;
use Afterburner\Voting\Support\SubscriptionEntitlementGate;
use Afterburner\Voting\Support\TeamVotingSettings;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProxiesController
{
    public function __invoke(Team $team): View
    {
        $user = Auth::user();

        abort_if(! config('afterburner-voting.proxy_grant_resolver'), 404);

        if (! $user->belongsToTeam($team) || $team->id !== $user->currentTeam?->id) {
            abort(403);
        }

        abort_unless(SubscriptionEntitlementGate::allows($team), 403);

        if (! TeamVotingSettings::allowProxyVotesForTeam($team)) {
            abort(403);
        }

        $resolver = app(ProxyGrantResolver::class);

        if (! $resolver->userCanAccess($user, $team)) {
            abort(403);
        }

        return view('afterburner-voting::proxies.index', [
            'team' => $team,
        ]);
    }
}
