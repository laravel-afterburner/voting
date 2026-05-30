<?php

namespace Afterburner\Voting\Tests\Unit;

use Afterburner\Voting\Support\BallotParticipation;
use Afterburner\Voting\Tests\TestCase;
use App\Models\User;

class BallotParticipationTest extends TestCase
{
    public function test_user_has_pending_vote_is_false_after_casting_vote(): void
    {
        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user, [
            'electorate' => \Afterburner\Voting\Enums\ElectorateType::AllMembers,
        ]);

        $resolver = app(\Afterburner\Voting\Contracts\VoterEligibilityResolver::class);

        $this->assertTrue(BallotParticipation::userHasPendingVote($user, $ballot, $resolver));

        $yes = $ballot->options->firstWhere('label', 'Yes');

        app(\Afterburner\Voting\Actions\CastVote::class)->execute(
            $ballot,
            $user,
            $yes,
            User::class,
            $user->id,
        );

        $ballot = $ballot->fresh();

        $this->assertFalse(BallotParticipation::userHasPendingVote($user, $ballot, $resolver));
    }
}
