<?php

namespace Afterburner\Voting\Tests\Unit;

use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Support\UserBallotResponseSummary;
use Afterburner\Voting\Tests\Support\TestMultiLotOwnerVoterEligibilityResolver;
use Afterburner\Voting\Tests\TestCase;
use App\Models\User;

class UserBallotResponseSummaryTest extends TestCase
{
    public function test_consolidates_matching_owned_lot_votes_without_lot_labels(): void
    {
        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user);
        $yes = $ballot->options->firstWhere('label', 'Yes');

        $responses = collect([
            $this->makeResponse($ballot, $user, $yes->id, TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 1),
            $this->makeResponse($ballot, $user, $yes->id, TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 2),
            $this->makeResponse($ballot, $user, $yes->id, TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 3),
        ]);

        config(['afterburner-voting.eligibility_resolver' => TestMultiLotOwnerVoterEligibilityResolver::class]);
        $this->app->forgetInstance(VoterEligibilityResolver::class);

        $summaries = app(UserBallotResponseSummary::class)->summarize($ballot, $responses);

        $this->assertCount(1, $summaries);
        $this->assertTrue($summaries->first()['consolidated']);
        $this->assertSame('Yes', $summaries->first()['option_label']);
        $this->assertNull($summaries->first()['unit_label']);
        $this->assertCount(3, $summaries->first()['response_ids']);
    }

    public function test_shows_individual_rows_when_owned_lot_votes_differ(): void
    {
        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user);
        $yes = $ballot->options->firstWhere('label', 'Yes');
        $no = $ballot->options->firstWhere('label', 'No');

        $responses = collect([
            $this->makeResponse($ballot, $user, $yes->id, TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 1),
            $this->makeResponse($ballot, $user, $yes->id, TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 2),
            $this->makeResponse($ballot, $user, $no->id, TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 3),
        ]);

        config(['afterburner-voting.eligibility_resolver' => TestMultiLotOwnerVoterEligibilityResolver::class]);
        $this->app->forgetInstance(VoterEligibilityResolver::class);

        $summaries = app(UserBallotResponseSummary::class)->summarize($ballot, $responses);

        $this->assertCount(3, $summaries);
        $this->assertFalse($summaries->contains(fn (array $row) => $row['consolidated']));
        $this->assertSame('Lot 1', $summaries->first()['unit_label']);
        $this->assertSame('Lot 3', $summaries->last()['unit_label']);
    }

    public function test_single_user_vote_has_no_unit_label(): void
    {
        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user);
        $yes = $ballot->options->firstWhere('label', 'Yes');

        $responses = collect([
            $this->makeResponse($ballot, $user, $yes->id, User::class, $user->id),
        ]);

        $summaries = app(UserBallotResponseSummary::class)->summarize($ballot, $responses);

        $this->assertCount(1, $summaries);
        $this->assertNull($summaries->first()['unit_label']);
    }

    public function test_shows_updated_option_after_vote_change(): void
    {
        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user);
        $yes = $ballot->options->firstWhere('label', 'Yes');
        $no = $ballot->options->firstWhere('label', 'No');

        $responses = collect([
            $this->makeResponse($ballot, $user, $yes->id, TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 1),
            $this->makeResponse($ballot, $user, $yes->id, TestMultiLotOwnerVoterEligibilityResolver::UNIT_TYPE, 2),
        ]);

        config(['afterburner-voting.eligibility_resolver' => TestMultiLotOwnerVoterEligibilityResolver::class]);
        $this->app->forgetInstance(VoterEligibilityResolver::class);

        $summary = app(UserBallotResponseSummary::class);
        $this->assertSame('Yes', $summary->summarize($ballot, $responses)->first()['option_label']);
        $this->assertCount(1, $summary->summarize($ballot, $responses));

        $responses->each(function (BallotResponse $response) use ($no) {
            $response->update(['ballot_option_id' => $no->id, 'cast_at' => now()]);
            $response->unsetRelation('option');
            $response->load('option');
        });

        $this->assertSame('No', $summary->summarize($ballot, $responses)->first()['option_label']);
    }

    protected function makeResponse($ballot, User $user, int $optionId, string $unitType, int $unitId): BallotResponse
    {
        return BallotResponse::query()->create([
            'ballot_id' => $ballot->id,
            'ballot_option_id' => $optionId,
            'cast_by_user_id' => $user->id,
            'voter_unit_type' => $unitType,
            'voter_unit_id' => $unitId,
            'cast_at' => now(),
        ])->load('option');
    }
}
