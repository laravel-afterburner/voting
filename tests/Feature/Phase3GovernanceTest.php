<?php

namespace Afterburner\Voting\Tests\Feature;

use Afterburner\Voting\Actions\CastVote;
use Afterburner\Voting\Actions\OpenBallot;
use Afterburner\Voting\Actions\PublishBallot;
use Afterburner\Voting\Actions\RevokeVote;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Enums\BallotType;
use Afterburner\Voting\Enums\ElectorateType;
use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Events\BallotOpened;
use Afterburner\Voting\Events\VoteRevoked;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Jobs\CloseScheduledBallot;
use Afterburner\Voting\Jobs\OpenScheduledBallot;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Services\BallotResultsPdfExporter;
use Afterburner\Voting\Services\BallotTallyService;
use Afterburner\Voting\Tests\Support\TestWeightedVoterEligibilityResolver;
use Afterburner\Voting\Tests\TestCase;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class Phase3GovernanceTest extends TestCase
{
    public function test_revoke_vote_removes_response_and_blocks_recast(): void
    {
        config(['afterburner-voting.allow_vote_revocation' => true]);
        Event::fake([VoteRevoked::class]);

        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user);
        $yes = $ballot->options->firstWhere('label', 'Yes');

        app(CastVote::class)->execute($ballot, $user, $yes, User::class, $user->id);

        $revocation = app(RevokeVote::class)->execute($ballot, $user, User::class, $user->id);

        $this->assertDatabaseMissing('ballot_responses', [
            'ballot_id' => $ballot->id,
            'voter_unit_type' => User::class,
            'voter_unit_id' => $user->id,
        ]);
        $this->assertDatabaseHas('ballot_vote_revocations', [
            'id' => $revocation->id,
            'ballot_id' => $ballot->id,
            'voter_unit_type' => User::class,
            'voter_unit_id' => $user->id,
        ]);

        Event::assertDispatched(VoteRevoked::class);

        $this->expectException(VotingException::class);
        $this->expectExceptionMessage('revoked its vote');

        app(CastVote::class)->execute($ballot, $user, $yes, User::class, $user->id);
    }

    public function test_revoke_vote_rejected_when_disabled(): void
    {
        config(['afterburner-voting.allow_vote_revocation' => false]);

        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user);
        $yes = $ballot->options->firstWhere('label', 'Yes');

        app(CastVote::class)->execute($ballot, $user, $yes, User::class, $user->id);

        $this->expectException(VotingException::class);
        $this->expectExceptionMessage('not enabled');

        app(RevokeVote::class)->execute($ballot, $user, User::class, $user->id);
    }

    public function test_second_revoke_attempt_is_rejected(): void
    {
        config(['afterburner-voting.allow_vote_revocation' => true]);

        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user);
        $yes = $ballot->options->firstWhere('label', 'Yes');

        app(CastVote::class)->execute($ballot, $user, $yes, User::class, $user->id);
        app(RevokeVote::class)->execute($ballot, $user, User::class, $user->id);

        $this->expectException(VotingException::class);
        $this->expectExceptionMessage('already been revoked');

        app(RevokeVote::class)->execute($ballot, $user, User::class, $user->id);
    }

    public function test_scheduled_ballot_opens_via_job(): void
    {
        Event::fake([BallotOpened::class]);

        [$user, $team] = $this->createTeamWithUser();
        $ballot = Ballot::query()->create([
            'team_id' => $team->id,
            'created_by_user_id' => $user->id,
            'title' => 'Scheduled AGM motion',
            'type' => BallotType::Resolution,
            'status' => BallotStatus::Scheduled,
            'electorate' => ElectorateType::AllMembers,
            'vote_visibility' => VoteVisibility::VisibleAfterClose,
            'opens_at' => now()->subMinute(),
            'closes_at' => now()->addWeek(),
            'published_at' => now(),
        ]);

        OpenScheduledBallot::dispatchSync($ballot->id);

        $ballot->refresh();
        $this->assertSame(BallotStatus::Open, $ballot->status);
        Event::assertDispatched(BallotOpened::class);
    }

    public function test_publish_dispatches_transition_jobs(): void
    {
        Queue::fake();

        [$user, $team] = $this->createTeamWithUser();
        $ballot = Ballot::query()->create([
            'team_id' => $team->id,
            'created_by_user_id' => $user->id,
            'title' => 'Future ballot',
            'type' => BallotType::Resolution,
            'status' => BallotStatus::Draft,
            'electorate' => ElectorateType::AllMembers,
            'vote_visibility' => VoteVisibility::VisibleAfterClose,
            'opens_at' => now()->addHour(),
            'closes_at' => now()->addDays(2),
        ]);
        $ballot->options()->createMany([
            ['label' => 'Yes', 'sort_order' => 0],
            ['label' => 'No', 'sort_order' => 1],
        ]);

        app(PublishBallot::class)->execute($ballot->fresh('options'), $user);

        Queue::assertPushed(OpenScheduledBallot::class);
        Queue::assertPushed(CloseScheduledBallot::class);
    }

    public function test_open_ballot_action_transitions_scheduled_to_open(): void
    {
        [$user, $team] = $this->createTeamWithUser();
        $ballot = Ballot::query()->create([
            'team_id' => $team->id,
            'created_by_user_id' => $user->id,
            'title' => 'Ready to open',
            'type' => BallotType::Resolution,
            'status' => BallotStatus::Scheduled,
            'electorate' => ElectorateType::AllMembers,
            'vote_visibility' => VoteVisibility::VisibleAfterClose,
            'opens_at' => now()->subMinute(),
            'closes_at' => now()->addWeek(),
            'published_at' => now(),
        ]);

        app(OpenBallot::class)->execute($ballot);

        $this->assertSame(BallotStatus::Open, $ballot->fresh()->status);
    }

    public function test_weighted_tally_uses_unit_entitlement(): void
    {
        config(['afterburner-voting.eligibility_resolver' => TestWeightedVoterEligibilityResolver::class]);
        $this->app->forgetInstance(VoterEligibilityResolver::class);

        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user);
        $yes = $ballot->options->firstWhere('label', 'Yes');
        $no = $ballot->options->firstWhere('label', 'No');

        app(CastVote::class)->execute(
            $ballot,
            $user,
            $yes,
            TestWeightedVoterEligibilityResolver::UNIT_TYPE,
            1,
        );
        app(CastVote::class)->execute(
            $ballot,
            $user,
            $yes,
            TestWeightedVoterEligibilityResolver::UNIT_TYPE,
            2,
        );
        app(CastVote::class)->execute(
            $ballot,
            $user,
            $no,
            TestWeightedVoterEligibilityResolver::UNIT_TYPE,
            3,
        );

        $tally = app(BallotTallyService::class)->tally($ballot);

        $this->assertTrue($tally['weighted']);
        $this->assertEqualsWithDelta(3.5, $tally['total_votes'], 0.001);

        $byLabel = collect($tally['options'])->keyBy('label');
        $this->assertEqualsWithDelta(3.0, $byLabel['Yes']['count'], 0.001);
        $this->assertEqualsWithDelta(0.5, $byLabel['No']['count'], 0.001);
        $this->assertEqualsWithDelta(85.7, $byLabel['Yes']['percentage'], 0.1);
        $this->assertEqualsWithDelta(14.3, $byLabel['No']['percentage'], 0.1);
    }

    public function test_pdf_export_available_when_dompdf_installed(): void
    {
        if (! class_exists(Pdf::class)) {
            $this->markTestSkipped('barryvdh/laravel-dompdf is not installed.');
        }

        [$user, $team] = $this->createTeamWithUser(['vote_resolutions', 'create_resolutions', 'export_ballot_results', 'view_ballot_results']);
        $ballot = $this->createOpenBallot($team, $user);

        app(CastVote::class)->execute(
            $ballot,
            $user,
            $ballot->options->firstWhere('label', 'Yes'),
            User::class,
            $user->id,
        );

        $ballot->update([
            'status' => BallotStatus::Closed,
            'closed_at' => now(),
        ]);
        $ballot = $ballot->fresh();

        $exporter = app(BallotResultsPdfExporter::class);
        $this->assertTrue($exporter->isAvailable());

        $response = $exporter->download($ballot);
        $this->assertStringContainsString('pdf', strtolower($response->headers->get('Content-Type') ?? ''));
    }
}
