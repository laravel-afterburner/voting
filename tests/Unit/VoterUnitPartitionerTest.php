<?php

namespace Afterburner\Voting\Tests\Unit;

use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Support\VoterUnit;
use Afterburner\Voting\Support\VoterUnitPartitioner;
use Afterburner\Voting\Tests\Support\TestMultiLotOwnerVoterEligibilityResolver;
use Afterburner\Voting\Tests\TestCase;
use App\Models\User;

class VoterUnitPartitionerTest extends TestCase
{
    public function test_partitions_owned_lots_separately_from_user_units(): void
    {
        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user);

        $partitioner = new VoterUnitPartitioner;
        $partition = $partitioner->partition(
            $user,
            $ballot,
            collect([
                new VoterUnit(TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 1),
                new VoterUnit(TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 2),
                new VoterUnit(User::class, $user->id),
            ]),
        );

        $this->assertCount(2, $partition['owned_lot_units']);
        $this->assertCount(0, $partition['proxy_units']);
        $this->assertCount(1, $partition['individual_units']);
        $this->assertTrue($partitioner->supportsBulkLotVoting($partition['owned_lot_units']));
    }

    public function test_single_owned_lot_does_not_support_bulk_voting(): void
    {
        $partitioner = new VoterUnitPartitioner;

        $this->assertFalse($partitioner->supportsBulkLotVoting(collect([
            new VoterUnit(TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 1),
        ])));
    }

    public function test_should_use_per_lot_form_when_owned_lot_votes_differ(): void
    {
        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user);
        $yes = $ballot->options->firstWhere('label', 'Yes');
        $no = $ballot->options->firstWhere('label', 'No');

        $ownedLotUnits = collect([
            new VoterUnit(TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 1),
            new VoterUnit(TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 2),
        ]);

        $responses = collect([
            BallotResponse::query()->create([
                'ballot_id' => $ballot->id,
                'ballot_option_id' => $yes->id,
                'cast_by_user_id' => $user->id,
                'voter_unit_type' => TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE,
                'voter_unit_id' => 1,
                'cast_at' => now(),
            ]),
            BallotResponse::query()->create([
                'ballot_id' => $ballot->id,
                'ballot_option_id' => $no->id,
                'cast_by_user_id' => $user->id,
                'voter_unit_type' => TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE,
                'voter_unit_id' => 2,
                'cast_at' => now(),
            ]),
        ]);

        $partitioner = new VoterUnitPartitioner;

        $this->assertTrue($partitioner->shouldUsePerLotVoteForm($ownedLotUnits, $responses));
    }

    public function test_should_use_bulk_form_when_owned_lot_votes_match(): void
    {
        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user);
        $yes = $ballot->options->firstWhere('label', 'Yes');

        $ownedLotUnits = collect([
            new VoterUnit(TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 1),
            new VoterUnit(TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 2),
        ]);

        $responses = collect([
            BallotResponse::query()->create([
                'ballot_id' => $ballot->id,
                'ballot_option_id' => $yes->id,
                'cast_by_user_id' => $user->id,
                'voter_unit_type' => TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE,
                'voter_unit_id' => 1,
                'cast_at' => now(),
            ]),
            BallotResponse::query()->create([
                'ballot_id' => $ballot->id,
                'ballot_option_id' => $yes->id,
                'cast_by_user_id' => $user->id,
                'voter_unit_type' => TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE,
                'voter_unit_id' => 2,
                'cast_at' => now(),
            ]),
        ]);

        $partitioner = new VoterUnitPartitioner;

        $this->assertFalse($partitioner->shouldUsePerLotVoteForm($ownedLotUnits, $responses));
    }
}
