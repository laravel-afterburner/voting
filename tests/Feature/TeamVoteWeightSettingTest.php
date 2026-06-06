<?php

namespace Afterburner\Voting\Tests\Feature;

use Afterburner\Voting\Actions\CastVote;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Services\BallotTallyService;
use Afterburner\Voting\Support\TeamVotingSettings;
use Afterburner\Voting\Tests\Support\TestWeightedVoterEligibilityResolver;
use Afterburner\Voting\Tests\TestCase;

class TeamVoteWeightSettingTest extends TestCase
{
    public function test_team_default_vote_weight_overrides_per_lot_entitlement(): void
    {
        config(['afterburner-voting.eligibility_resolver' => TestWeightedVoterEligibilityResolver::class]);
        $this->app->forgetInstance(VoterEligibilityResolver::class);

        [$user, $team] = $this->createTeamWithUser();
        TeamVotingSettings::forTeam($team)->update(['default_vote_weight_per_lot' => 1]);

        $ballot = $this->createOpenBallot($team, $user);
        $yes = $ballot->options->firstWhere('label', 'Yes');

        app(CastVote::class)->execute(
            $ballot,
            $user,
            $yes,
            TestWeightedVoterEligibilityResolver::UNIT_TYPE,
            2,
        );

        $tally = app(BallotTallyService::class)->tally($ballot);

        $this->assertTrue($tally['weighted']);
        $this->assertEqualsWithDelta(1.0, $tally['total_votes'], 0.001);
        $this->assertTrue(TeamVotingSettings::usesPerLotVoteWeights($team) === false);
    }

    public function test_null_team_default_uses_resolver_entitlement(): void
    {
        config(['afterburner-voting.eligibility_resolver' => TestWeightedVoterEligibilityResolver::class]);
        $this->app->forgetInstance(VoterEligibilityResolver::class);

        [$user, $team] = $this->createTeamWithUser();
        TeamVotingSettings::forTeam($team)->update(['default_vote_weight_per_lot' => null]);

        $ballot = $this->createOpenBallot($team, $user);
        $yes = $ballot->options->firstWhere('label', 'Yes');

        app(CastVote::class)->execute(
            $ballot,
            $user,
            $yes,
            TestWeightedVoterEligibilityResolver::UNIT_TYPE,
            2,
        );

        $tally = app(BallotTallyService::class)->tally($ballot);

        $this->assertTrue($tally['weighted']);
        $this->assertEqualsWithDelta(2.0, $tally['total_votes'], 0.001);
        $this->assertTrue(TeamVotingSettings::usesPerLotVoteWeights($team));
    }
}
