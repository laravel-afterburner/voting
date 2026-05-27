<?php

namespace Afterburner\Voting\Tests\Unit;

use Afterburner\Voting\Actions\CastVote;
use Afterburner\Voting\Services\BallotTallyService;
use Afterburner\Voting\Tests\TestCase;
use App\Models\User;

class BallotTallyServiceTest extends TestCase
{
    public function test_tally_returns_counts_and_percentages(): void
    {
        [$user, $team] = $this->createTeamWithUser(['vote_resolutions']);
        $voterTwo = $this->createAdditionalUser($team, ['vote_resolutions'], 'voter2@example.com');
        $ballot = $this->createOpenBallot($team, $user);

        app(CastVote::class)->execute(
            $ballot,
            $user,
            $ballot->options->firstWhere('label', 'Yes'),
            User::class,
            $user->id,
        );

        app(CastVote::class)->execute(
            $ballot,
            $voterTwo,
            $ballot->options->firstWhere('label', 'No'),
            User::class,
            $voterTwo->id,
        );

        $tally = app(BallotTallyService::class)->tally($ballot);

        $this->assertEqualsWithDelta(2, $tally['total_votes'], 0.001);
        $this->assertEqualsWithDelta(1, collect($tally['options'])->firstWhere('label', 'Yes')['count'], 0.001);
        $this->assertSame(50.0, collect($tally['options'])->firstWhere('label', 'Yes')['percentage']);
    }
}
