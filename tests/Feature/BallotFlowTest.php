<?php

namespace Afterburner\Voting\Tests\Feature;

use Afterburner\Voting\Actions\CastVote;
use Afterburner\Voting\Actions\CloseBallot;
use Afterburner\Voting\Actions\CreateBallot;
use Afterburner\Voting\Actions\PublishBallot;
use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Enums\BallotType;
use Afterburner\Voting\Enums\ElectorateType;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Services\BallotTallyService;
use Afterburner\Voting\Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;

class BallotFlowTest extends TestCase
{
    public function test_create_publish_vote_and_close_flow(): void
    {
        [$creator, $team] = $this->createTeamWithUser(['vote_resolutions', 'create_resolutions']);
        $voter = $this->createAdditionalUser($team, ['vote_resolutions'], 'voter@example.com');

        $opensAt = now()->subHour();
        $closesAt = now()->addWeek();

        $ballot = app(CreateBallot::class)->execute(
            $team,
            $creator,
            'Approve budget',
            'Annual budget vote',
            BallotType::Resolution,
            ElectorateType::AllMembers,
            [
                ['label' => 'Yes'],
                ['label' => 'No'],
            ],
            null,
            null,
            $opensAt,
            $closesAt,
        );

        $this->assertSame(BallotStatus::Draft, $ballot->status);

        $ballot = app(PublishBallot::class)->execute($ballot, $creator);
        $this->assertSame(BallotStatus::Open, $ballot->status);

        app(CastVote::class)->execute(
            $ballot,
            $voter,
            $ballot->options->firstWhere('label', 'Yes'),
            User::class,
            $voter->id,
        );

        $this->assertDatabaseCount('ballot_responses', 1);

        $ballot = app(CloseBallot::class)->execute($ballot, $creator);
        $this->assertSame(BallotStatus::Closed, $ballot->status);

        $tally = app(BallotTallyService::class)->tally($ballot);
        $this->assertEqualsWithDelta(1, $tally['total_votes'], 0.001);
        $this->assertEqualsWithDelta(1, collect($tally['options'])->firstWhere('label', 'Yes')['count'], 0.001);
    }

    public function test_publish_seeds_yes_no_options_for_resolution_when_missing(): void
    {
        [$creator, $team] = $this->createTeamWithUser(['create_resolutions']);

        $ballot = app(CreateBallot::class)->execute(
            $team,
            $creator,
            'Resolution without options',
            null,
            BallotType::Resolution,
            ElectorateType::AllMembers,
            [],
            null,
            null,
            now()->subHour(),
            now()->addWeek(),
        );

        $ballot = app(PublishBallot::class)->execute($ballot, $creator);

        $this->assertCount(2, $ballot->options);
        $this->assertSame(['Yes', 'No'], $ballot->options->pluck('label')->all());
    }

    public function test_publish_requires_open_and_close_dates(): void
    {
        [$creator, $team] = $this->createTeamWithUser(['create_resolutions']);

        $ballot = app(CreateBallot::class)->execute(
            $team,
            $creator,
            'Missing schedule',
            null,
            BallotType::Resolution,
            ElectorateType::AllMembers,
            [
                ['label' => 'Yes'],
                ['label' => 'No'],
            ],
        );

        $this->expectException(VotingException::class);
        $this->expectExceptionMessage('Open and close dates are required');

        app(PublishBallot::class)->execute($ballot, $creator);
    }

    public function test_publish_rejects_close_date_before_open_date(): void
    {
        [$creator, $team] = $this->createTeamWithUser(['create_resolutions']);

        $ballot = app(CreateBallot::class)->execute(
            $team,
            $creator,
            'Invalid schedule',
            null,
            BallotType::Resolution,
            ElectorateType::AllMembers,
            [
                ['label' => 'Yes'],
                ['label' => 'No'],
            ],
            null,
            null,
            Carbon::parse('2026-06-01 12:00:00'),
            Carbon::parse('2026-06-01 10:00:00'),
        );

        $this->expectException(VotingException::class);
        $this->expectExceptionMessage('Close date must be after the open date');

        app(PublishBallot::class)->execute($ballot, $creator);
    }
}
