<?php

namespace Afterburner\Voting\Tests\Feature;

use Afterburner\Voting\Actions\CastVote;
use Afterburner\Voting\Actions\CloseBallot;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Tests\Support\TestPropertyVoterEligibilityResolver;
use Afterburner\Voting\Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VotingInvariantsTest extends TestCase
{
    public function test_same_user_can_change_vote_while_ballot_is_open(): void
    {
        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user, [
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addDay(),
        ]);
        $yesOption = $ballot->options->firstWhere('label', 'Yes');
        $noOption = $ballot->options->firstWhere('label', 'No');

        app(CastVote::class)->execute(
            $ballot,
            $user,
            $yesOption,
            User::class,
            $user->id,
        );

        $response = app(CastVote::class)->execute(
            $ballot,
            $user,
            $noOption,
            User::class,
            $user->id,
        );

        $this->assertDatabaseCount('ballot_responses', 1);
        $this->assertSame($noOption->id, $response->ballot_option_id);
    }

    public function test_vote_cannot_be_changed_after_ballot_closes(): void
    {
        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user, [
            'opens_at' => now()->subDays(2),
            'closes_at' => now()->subHour(),
        ]);

        $this->expectException(VotingException::class);
        $this->expectExceptionMessage('This ballot is not open for voting.');

        app(CastVote::class)->execute(
            $ballot,
            $user,
            $ballot->options->firstWhere('label', 'Yes'),
            User::class,
            $user->id,
        );
    }

    public function test_designated_voter_swap_cannot_revote_same_unit(): void
    {
        config(['afterburner-voting.eligibility_resolver' => TestPropertyVoterEligibilityResolver::class]);
        $this->app->forgetInstance(VoterEligibilityResolver::class);

        [$owner, $team] = $this->createTeamWithUser();
        $successor = $this->createAdditionalUser($team, ['vote_resolutions'], 'successor@example.com');
        $ballot = $this->createOpenBallot($team, $owner, [
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addDay(),
        ]);
        $yesOption = $ballot->options->firstWhere('label', 'Yes');

        app(CastVote::class)->execute(
            $ballot,
            $owner,
            $yesOption,
            TestPropertyVoterEligibilityResolver::UNIT_TYPE,
            1,
        );

        $this->expectException(VotingException::class);
        $this->expectExceptionMessage('This voting unit has already cast a vote.');

        app(CastVote::class)->execute(
            $ballot,
            $successor,
            $ballot->options->firstWhere('label', 'No'),
            TestPropertyVoterEligibilityResolver::UNIT_TYPE,
            1,
        );
    }

    public function test_proxy_holder_cannot_double_vote_for_grantor_unit(): void
    {
        config(['afterburner-voting.eligibility_resolver' => TestPropertyVoterEligibilityResolver::class]);
        $this->app->forgetInstance(VoterEligibilityResolver::class);

        [$grantor, $team] = $this->createTeamWithUser();
        $proxyHolder = $this->createAdditionalUser($team, ['vote_resolutions'], 'proxy@example.com');
        $ballot = $this->createOpenBallot($team, $grantor);
        $yesOption = $ballot->options->firstWhere('label', 'Yes');

        app(CastVote::class)->execute(
            $ballot,
            $grantor,
            $yesOption,
            TestPropertyVoterEligibilityResolver::UNIT_TYPE,
            1,
        );

        $this->expectException(VotingException::class);

        app(CastVote::class)->execute(
            $ballot,
            $proxyHolder,
            $yesOption,
            TestPropertyVoterEligibilityResolver::UNIT_TYPE,
            1,
        );
    }

    public function test_closed_ballot_rejects_votes(): void
    {
        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user);

        app(CloseBallot::class)->execute($ballot, $user);

        $this->expectException(VotingException::class);
        $this->expectExceptionMessage('This ballot is not open for voting.');

        app(CastVote::class)->execute(
            $ballot->fresh(),
            $user,
            $ballot->options->firstWhere('label', 'Yes'),
            User::class,
            $user->id,
        );
    }

    public function test_wrong_team_ballot_is_rejected(): void
    {
        [$user, $team] = $this->createTeamWithUser();

        $otherUser = User::query()->create([
            'name' => 'Other Owner',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $otherTeam = Team::query()->create([
            'name' => 'Other Team',
            'user_id' => $otherUser->id,
        ]);
        $otherTeam->users()->attach($otherUser);
        $otherUser->update(['current_team_id' => $otherTeam->id]);
        $this->createRoleWithPermissions('other_member', ['vote_resolutions', 'create_resolutions']);
        DB::table('user_role')->insert([
            'user_id' => $otherUser->id,
            'role_id' => DB::table('roles')->where('slug', 'other_member')->value('id'),
            'team_id' => $otherTeam->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ballot = $this->createOpenBallot($otherTeam, $otherUser);

        $this->assertFalse(Gate::forUser($user)->allows('vote', $ballot));

        $this->expectException(AuthorizationException::class);

        app(CastVote::class)->execute(
            $ballot,
            $user,
            $ballot->options->firstWhere('label', 'Yes'),
            User::class,
            $user->id,
        );
    }

    public function test_user_without_vote_permission_cannot_cast(): void
    {
        [$creator, $team] = $this->createTeamWithUser(['create_resolutions']);
        $viewer = $this->createAdditionalUser($team, [], 'novote@example.com');
        $ballot = $this->createOpenBallot($team, $creator);

        $this->assertFalse(Gate::forUser($viewer)->allows('vote', $ballot));

        $this->expectException(AuthorizationException::class);

        app(CastVote::class)->execute(
            $ballot,
            $viewer,
            $ballot->options->firstWhere('label', 'Yes'),
            User::class,
            $viewer->id,
        );
    }
}
