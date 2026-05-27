<?php

namespace Afterburner\Voting\Tests\Unit;

use Afterburner\Voting\Resolvers\DefaultUserVoterEligibilityResolver;
use Afterburner\Voting\Tests\TestCase;
use App\Models\User;

class DefaultUserVoterEligibilityResolverTest extends TestCase
{
    public function test_eligible_voter_unit_is_the_user_themselves(): void
    {
        [$user, $team] = $this->createTeamWithUser(['vote_resolutions']);
        $ballot = $this->createOpenBallot($team, $user);

        $resolver = app(DefaultUserVoterEligibilityResolver::class);
        $units = $resolver->eligibleVoterUnits($user, $ballot);

        $this->assertCount(1, $units);
        $this->assertTrue($units->first()->matches(User::class, $user->id));
    }

    public function test_total_eligible_units_counts_team_members_with_vote_permission(): void
    {
        [$user, $team] = $this->createTeamWithUser(['vote_resolutions']);
        $this->createAdditionalUser($team, ['vote_resolutions'], 'voter2@example.com');
        $this->createAdditionalUser($team, [], 'novote@example.com');

        $ballot = $this->createOpenBallot($team, $user);
        $resolver = app(DefaultUserVoterEligibilityResolver::class);

        $this->assertSame(2, $resolver->totalEligibleVoterUnits($ballot));
    }
}
