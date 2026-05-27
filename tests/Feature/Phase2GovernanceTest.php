<?php

namespace Afterburner\Voting\Tests\Feature;

use Afterburner\Voting\Actions\CastVote;
use Afterburner\Voting\Actions\CloseBallot;
use Afterburner\Voting\Actions\CreateProxy;
use Afterburner\Voting\Actions\PublishBallot;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Enums\ElectorateType;
use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Events\BallotClosed;
use Afterburner\Voting\Events\BallotPublished;
use Afterburner\Voting\Events\VoteCast;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Resolvers\DefaultUserVoterEligibilityResolver;
use Afterburner\Voting\Services\BallotTallyService;
use Afterburner\Voting\Services\QuorumService;
use Afterburner\Voting\Tests\Support\TestMultiUnitVoterEligibilityResolver;
use Afterburner\Voting\Tests\Support\TestPropertyVoterEligibilityResolver;
use Afterburner\Voting\Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

class Phase2GovernanceTest extends TestCase
{
    public function test_proxy_holder_blocked_when_grantor_unit_already_voted(): void
    {
        config(['afterburner-voting.eligibility_resolver' => TestPropertyVoterEligibilityResolver::class]);
        $this->app->forgetInstance(VoterEligibilityResolver::class);

        [$grantor, $team] = $this->createTeamWithUser();
        $proxyHolder = $this->createAdditionalUser($team, ['vote_resolutions'], 'proxy@example.com');
        $ballot = $this->createOpenBallot($team, $grantor);

        app(CreateProxy::class)->execute(
            $ballot,
            $grantor,
            $proxyHolder,
            TestPropertyVoterEligibilityResolver::UNIT_TYPE,
            1,
        );

        app(CastVote::class)->execute(
            $ballot,
            $grantor,
            $ballot->options->firstWhere('label', 'Yes'),
            TestPropertyVoterEligibilityResolver::UNIT_TYPE,
            1,
        );

        $this->expectException(VotingException::class);
        $this->expectExceptionMessage('This voting unit has already cast a vote.');

        app(CastVote::class)->execute(
            $ballot,
            $proxyHolder,
            $ballot->options->firstWhere('label', 'No'),
            TestPropertyVoterEligibilityResolver::UNIT_TYPE,
            1,
        );
    }

    public function test_proxy_holder_can_cast_for_grantor_unit(): void
    {
        config(['afterburner-voting.eligibility_resolver' => TestPropertyVoterEligibilityResolver::class]);
        $this->app->forgetInstance(VoterEligibilityResolver::class);

        [$grantor, $team] = $this->createTeamWithUser();
        $proxyHolder = $this->createAdditionalUser($team, ['vote_resolutions'], 'proxy@example.com');
        $ballot = $this->createOpenBallot($team, $grantor);

        $proxy = app(CreateProxy::class)->execute(
            $ballot,
            $grantor,
            $proxyHolder,
            TestPropertyVoterEligibilityResolver::UNIT_TYPE,
            1,
        );

        $response = app(CastVote::class)->execute(
            $ballot,
            $proxyHolder,
            $ballot->options->firstWhere('label', 'Yes'),
            TestPropertyVoterEligibilityResolver::UNIT_TYPE,
            1,
            null,
            null,
            $proxy->id,
        );

        $this->assertSame($proxy->id, $response->proxy_vote_id);
        $this->assertSame($proxyHolder->id, $response->cast_by_user_id);
    }

    public function test_council_electorate_excludes_plain_owner(): void
    {
        config(['afterburner-voting.eligibility_resolver' => DefaultUserVoterEligibilityResolver::class]);
        $this->app->forgetInstance(VoterEligibilityResolver::class);

        [$creator, $team] = $this->createTeamWithUser(['create_resolutions']);
        $owner = $this->createAdditionalUser($team, ['vote_resolutions'], 'owner@example.com');
        $this->assignRoleToUser($owner, $team, 'strata_owner');

        $councilMember = $this->createAdditionalUser($team, ['vote_resolutions'], 'council@example.com');
        $this->assignRoleToUser($councilMember, $team, 'president');

        $ballot = $this->createOpenBallot($team, $creator, [
            'electorate' => ElectorateType::Council,
        ]);

        $resolver = app(VoterEligibilityResolver::class);

        $this->assertTrue($resolver->canCastVote($councilMember, $ballot, User::class, $councilMember->id));
        $this->assertFalse($resolver->canCastVote($owner, $ballot, User::class, $owner->id));
        $this->assertFalse(Gate::forUser($owner)->allows('vote', $ballot));
    }

    public function test_quorum_math_with_multi_unit_resolver(): void
    {
        config(['afterburner-voting.eligibility_resolver' => TestMultiUnitVoterEligibilityResolver::class]);
        $this->app->forgetInstance(VoterEligibilityResolver::class);

        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user, [
            'quorum_percent' => 50.00,
            'quorum_basis' => 'eligible_units',
        ]);

        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20] as $unitId) {
            app(CastVote::class)->execute(
                $ballot,
                $user,
                $ballot->options->firstWhere('label', 'Yes'),
                TestMultiUnitVoterEligibilityResolver::UNIT_TYPE,
                $unitId,
            );
        }

        $quorum = app(QuorumService::class)->calculate($ballot);

        $this->assertTrue($quorum['configured']);
        $this->assertSame(40, $quorum['eligible']);
        $this->assertSame(20, $quorum['cast']);
        $this->assertSame(50.0, $quorum['percent']);
        $this->assertTrue($quorum['met']);
    }

    public function test_secret_ballot_hides_response_details_until_closed(): void
    {
        [$user, $team] = $this->createTeamWithUser(['vote_resolutions', 'view_ballot_results']);
        $ballot = $this->createOpenBallot($team, $user, [
            'vote_visibility' => VoteVisibility::Secret,
        ]);

        app(CastVote::class)->execute(
            $ballot,
            $user,
            $ballot->options->firstWhere('label', 'Yes'),
            User::class,
            $user->id,
        );

        $tallyService = app(BallotTallyService::class);

        $this->assertFalse($tallyService->canViewTally($ballot));
        $this->assertEmpty($tallyService->responseDetails($ballot));

        $ballot->update(['status' => BallotStatus::Closed, 'closed_at' => now()]);

        $this->assertTrue($tallyService->canViewTally($ballot->fresh()));
        $this->assertEmpty($tallyService->responseDetails($ballot->fresh()));
        $this->assertEqualsWithDelta(1, $tallyService->tally($ballot->fresh())['total_votes'], 0.001);
    }

    public function test_visible_after_close_shows_details_only_when_closed(): void
    {
        [$user, $team] = $this->createTeamWithUser(['vote_resolutions', 'view_ballot_results']);
        $ballot = $this->createOpenBallot($team, $user, [
            'vote_visibility' => VoteVisibility::VisibleAfterClose,
        ]);

        app(CastVote::class)->execute(
            $ballot,
            $user,
            $ballot->options->firstWhere('label', 'Yes'),
            User::class,
            $user->id,
        );

        $tallyService = app(BallotTallyService::class);
        $this->assertEmpty($tallyService->responseDetails($ballot));

        $ballot->update(['status' => BallotStatus::Closed, 'closed_at' => now()]);
        $this->assertCount(1, $tallyService->responseDetails($ballot->fresh()));
    }

    public function test_race_condition_second_cast_fails_on_unique_constraint(): void
    {
        [$userA, $team] = $this->createTeamWithUser();
        $userB = $this->createAdditionalUser($team, ['vote_resolutions'], 'racer@example.com');

        config(['afterburner-voting.eligibility_resolver' => TestPropertyVoterEligibilityResolver::class]);
        $this->app->forgetInstance(VoterEligibilityResolver::class);

        $ballot = $this->createOpenBallot($team, $userA);
        $option = $ballot->options->firstWhere('label', 'Yes');

        app(CastVote::class)->execute(
            $ballot,
            $userA,
            $option,
            TestPropertyVoterEligibilityResolver::UNIT_TYPE,
            1,
        );

        $this->expectException(VotingException::class);
        $this->expectExceptionMessage('This voting unit has already cast a vote.');

        app(CastVote::class)->execute(
            $ballot,
            $userB,
            $option,
            TestPropertyVoterEligibilityResolver::UNIT_TYPE,
            1,
        );
    }

    public function test_audit_events_are_dispatched(): void
    {
        Event::fake([BallotPublished::class, BallotClosed::class, VoteCast::class]);

        [$user, $team] = $this->createTeamWithUser(['vote_resolutions', 'create_resolutions']);
        $ballot = $this->createOpenBallot($team, $user, ['status' => BallotStatus::Draft]);

        $ballot = app(PublishBallot::class)->execute($ballot, $user);
        Event::assertDispatched(BallotPublished::class);

        app(CastVote::class)->execute(
            $ballot,
            $user,
            $ballot->options->firstWhere('label', 'Yes'),
            User::class,
            $user->id,
        );
        Event::assertDispatched(VoteCast::class);

        app(CloseBallot::class)->execute($ballot, $user);
        Event::assertDispatched(BallotClosed::class);
    }

    protected function assignRoleToUser(User $user, $team, string $roleSlug): void
    {
        $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');

        if (! $roleId) {
            $roleId = $this->createRoleWithPermissions($roleSlug, ['vote_resolutions']);
        }

        DB::table('user_role')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'team_id' => $team->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
