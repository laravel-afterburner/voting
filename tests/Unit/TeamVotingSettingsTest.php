<?php

namespace Afterburner\Voting\Tests\Unit;

use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Support\TeamVotingSettings;
use Afterburner\Voting\Tests\TestCase;

class TeamVotingSettingsTest extends TestCase
{
    public function test_for_team_creates_settings_with_config_defaults(): void
    {
        [, $team] = $this->createTeamWithUser(['create_resolutions']);

        config([
            'afterburner-voting.default_quorum_percent' => 50,
            'afterburner-voting.default_vote_visibility' => 'secret',
            'afterburner-voting.allow_proxy_votes' => true,
        ]);

        TeamVotingSettings::forTeam($team);

        $this->assertDatabaseHas('team_voting_settings', [
            'team_id' => $team->id,
            'default_quorum_percent' => 50,
            'default_vote_visibility' => 'secret',
            'allow_proxy_votes' => 1,
            'lock_designation_during_open_ballots' => 0,
        ]);
    }

    public function test_team_overrides_take_precedence_over_config(): void
    {
        [, $team] = $this->createTeamWithUser(['create_resolutions']);

        config([
            'afterburner-voting.default_quorum_percent' => 50,
            'afterburner-voting.default_vote_visibility' => 'secret',
            'afterburner-voting.allow_proxy_votes' => true,
        ]);

        $settings = TeamVotingSettings::forTeam($team);
        $settings->update([
            'default_quorum_percent' => 75,
            'default_vote_visibility' => VoteVisibility::VisibleRealtime,
            'allow_proxy_votes' => false,
            'lock_designation_during_open_ballots' => true,
        ]);

        $this->assertSame(75.0, TeamVotingSettings::defaultQuorumPercentForTeam($team));
        $this->assertSame(VoteVisibility::VisibleRealtime, TeamVotingSettings::defaultVoteVisibilityForTeam($team));
        $this->assertFalse(TeamVotingSettings::allowProxyVotesForTeam($team));
        $this->assertTrue(TeamVotingSettings::lockDesignationDuringOpenBallotsForTeam($team));
    }

    public function test_global_proxy_kill_switch_overrides_team_setting(): void
    {
        [, $team] = $this->createTeamWithUser(['create_resolutions']);

        config(['afterburner-voting.allow_proxy_votes' => false]);

        $settings = TeamVotingSettings::forTeam($team);
        $settings->update(['allow_proxy_votes' => true]);

        $this->assertFalse(TeamVotingSettings::allowProxyVotesForTeam($team));
    }
}
