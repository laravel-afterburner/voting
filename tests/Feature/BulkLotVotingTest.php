<?php

namespace Afterburner\Voting\Tests\Feature;

use Afterburner\Voting\Actions\CastVotes;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Support\VoterUnit;
use Afterburner\Voting\Tests\Support\TestMultiLotOwnerVoterEligibilityResolver;
use Afterburner\Voting\Tests\TestCase;

class BulkLotVotingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['afterburner-voting.eligibility_resolver' => TestMultiLotOwnerVoterEligibilityResolver::class]);
        $this->app->forgetInstance(VoterEligibilityResolver::class);
    }

    public function test_cast_votes_records_one_response_per_lot(): void
    {
        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user);
        $yes = $ballot->options->firstWhere('label', 'Yes');

        $units = collect(TestMultiLotOwnerVoterEligibilityResolver::OWNED_LOT_IDS)
            ->map(fn (int $id) => new VoterUnit(TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, $id));

        app(CastVotes::class)->execute($ballot, $user, $yes, $units);

        $this->assertSame(3, BallotResponse::query()->where('ballot_id', $ballot->id)->count());

        foreach (TestMultiLotOwnerVoterEligibilityResolver::OWNED_LOT_IDS as $lotId) {
            $this->assertDatabaseHas('ballot_responses', [
                'ballot_id' => $ballot->id,
                'ballot_option_id' => $yes->id,
                'cast_by_user_id' => $user->id,
                'voter_unit_type' => TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE,
                'voter_unit_id' => $lotId,
            ]);
        }
    }

    public function test_cast_votes_updates_existing_owner_votes(): void
    {
        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user);
        $yes = $ballot->options->firstWhere('label', 'Yes');
        $no = $ballot->options->firstWhere('label', 'No');

        $units = collect([
            new VoterUnit(TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 1),
            new VoterUnit(TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 2),
        ]);

        app(CastVotes::class)->execute($ballot, $user, $yes, $units);
        app(CastVotes::class)->execute($ballot, $user, $no, $units);

        $this->assertSame(2, BallotResponse::query()->where('ballot_id', $ballot->id)->count());
        $this->assertSame(
            2,
            BallotResponse::query()
                ->where('ballot_id', $ballot->id)
                ->where('ballot_option_id', $no->id)
                ->count()
        );
    }
}
