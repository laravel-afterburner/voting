<?php

namespace Afterburner\Voting\Support;

use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Models\TeamVotingSetting;
use App\Models\Team;

class TeamVotingSettings
{
    public static function defaultQuorumPercentForTeam(Team|int $team): ?float
    {
        $setting = static::findForTeam($team);

        if ($setting && $setting->default_quorum_percent !== null) {
            return (float) $setting->default_quorum_percent;
        }

        $config = config('afterburner-voting.default_quorum_percent');

        return $config !== null ? (float) $config : null;
    }

    public static function defaultVoteVisibilityForTeam(Team|int $team): VoteVisibility
    {
        $setting = static::findForTeam($team);

        if ($setting) {
            return $setting->default_vote_visibility;
        }

        return VoteVisibility::from(config('afterburner-voting.default_vote_visibility', 'visible_after_close'));
    }

    public static function allowProxyVotesForTeam(Team|int $team): bool
    {
        if (! config('afterburner-voting.allow_proxy_votes', true)) {
            return false;
        }

        $setting = static::findForTeam($team);

        if ($setting) {
            return $setting->allow_proxy_votes;
        }

        return (bool) config('afterburner-voting.allow_proxy_votes', true);
    }

    public static function lockDesignationDuringOpenBallotsForTeam(Team|int $team): bool
    {
        $setting = static::findForTeam($team);

        return $setting?->lock_designation_during_open_ballots ?? false;
    }

    /**
     * Get or create voting settings for a team.
     */
    public static function forTeam(Team|int $team): TeamVotingSetting
    {
        $teamId = $team instanceof Team ? $team->id : $team;

        return TeamVotingSetting::query()->firstOrCreate(
            ['team_id' => $teamId],
            [
                'default_quorum_percent' => config('afterburner-voting.default_quorum_percent'),
                'default_vote_visibility' => config('afterburner-voting.default_vote_visibility', 'visible_after_close'),
                'allow_proxy_votes' => (bool) config('afterburner-voting.allow_proxy_votes', true),
                'lock_designation_during_open_ballots' => false,
            ]
        );
    }

    protected static function findForTeam(Team|int $team): ?TeamVotingSetting
    {
        $teamId = $team instanceof Team ? $team->id : $team;

        return TeamVotingSetting::query()->where('team_id', $teamId)->first();
    }
}
