<?php

namespace Afterburner\Voting\Tests\Feature;

use Afterburner\Voting\Actions\CreateBallot;
use Afterburner\Voting\Enums\BallotType;
use Afterburner\Voting\Enums\ElectorateType;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Support\SubscriptionEntitlementGate;
use Afterburner\Voting\Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Traits\SimulatesSubscriptionEntitlements;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SubscriptionEntitlementGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SimulatesSubscriptionEntitlements::clearSimulatedPlanFeatures();
        config(['afterburner-subscriptions.enabled' => true]);
    }

    public function test_access_allowed_when_subscriptions_disabled(): void
    {
        config(['afterburner-subscriptions.enabled' => false]);

        [$user, $team] = $this->createSubscribedTeamWithUser(
            permissions: ['create_resolutions'],
            planFeatures: ['features' => []],
            trialEndsAt: null,
        );

        $this->assertTrue(SubscriptionEntitlementGate::allows($team));
        $this->assertTrue($user->can('create', [Ballot::class, $team]));
    }

    public function test_access_allowed_when_team_does_not_implement_subscription_methods(): void
    {
        $plainTeam = new class extends Model {};

        $this->assertTrue(SubscriptionEntitlementGate::allows($plainTeam));
        $this->assertTrue(SubscriptionEntitlementGate::withinLimit($plainTeam, 'max_open_ballots', 99));
    }

    public function test_access_allowed_during_generic_trial_without_plan_feature(): void
    {
        [$user, $team] = $this->createSubscribedTeamWithUser(
            permissions: ['create_resolutions'],
            planFeatures: ['features' => []],
            trialEndsAt: now()->addWeek(),
        );

        $this->assertTrue($team->onGenericTrial());
        $this->assertTrue(SubscriptionEntitlementGate::allows($team));
        $this->assertTrue($user->can('create', [Ballot::class, $team]));
    }

    public function test_access_denied_after_trial_when_plan_lacks_voting_feature(): void
    {
        [$user, $team] = $this->createSubscribedTeamWithUser(
            permissions: ['create_resolutions'],
            planFeatures: ['features' => ['documents']],
            trialEndsAt: now()->subDay(),
        );

        $this->assertFalse($team->onGenericTrial());
        $this->assertFalse(SubscriptionEntitlementGate::allows($team));
        $this->assertFalse($user->can('create', [Ballot::class, $team]));
    }

    public function test_access_allowed_after_trial_when_plan_includes_voting_feature(): void
    {
        [$user, $team] = $this->createSubscribedTeamWithUser(
            permissions: ['create_resolutions'],
            planFeatures: ['features' => ['voting']],
            trialEndsAt: now()->subDay(),
        );

        $this->assertFalse($team->onGenericTrial());
        $this->assertTrue(SubscriptionEntitlementGate::allows($team));
        $this->assertTrue($user->can('create', [Ballot::class, $team]));
    }

    public function test_create_ballot_action_denied_without_entitlement(): void
    {
        [$user, $team] = $this->createSubscribedTeamWithUser(
            permissions: ['create_resolutions'],
            planFeatures: ['features' => []],
            trialEndsAt: now()->subDay(),
        );

        $this->expectException(AuthorizationException::class);

        app(CreateBallot::class)->execute(
            $team,
            $user,
            'Blocked ballot',
            null,
            BallotType::Resolution,
            ElectorateType::AllMembers,
            [['label' => 'Yes'], ['label' => 'No']],
        );
    }

    public function test_view_any_denied_without_entitlement(): void
    {
        [$user, $team] = $this->createSubscribedTeamWithUser(
            permissions: ['vote_resolutions', 'create_resolutions'],
            planFeatures: ['features' => []],
            trialEndsAt: now()->subDay(),
        );

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', Ballot::class));
    }

    public function test_open_ballot_view_denied_without_entitlement_even_with_permission(): void
    {
        [$creator, $team] = $this->createSubscribedTeamWithUser(
            permissions: ['vote_resolutions', 'create_resolutions'],
            planFeatures: ['features' => []],
            trialEndsAt: now()->subDay(),
        );

        $ballot = $this->createOpenBallot($team, $creator);

        $this->assertFalse($creator->can('view', $ballot));
        $this->assertFalse($creator->can('vote', $ballot));
    }

    public function test_within_limit_bypasses_when_subscriptions_disabled(): void
    {
        config(['afterburner-subscriptions.enabled' => false]);

        [, $team] = $this->createSubscribedTeamWithUser(
            planFeatures: ['max_open_ballots' => 1],
            trialEndsAt: null,
        );

        $this->assertTrue(SubscriptionEntitlementGate::withinLimit($team, 'max_open_ballots', 99));
    }

    public function test_within_limit_allowed_during_trial(): void
    {
        [, $team] = $this->createSubscribedTeamWithUser(
            planFeatures: ['max_open_ballots' => 1],
            trialEndsAt: now()->addWeek(),
        );

        $this->assertTrue(SubscriptionEntitlementGate::withinLimit($team, 'max_open_ballots', 99));
    }

    public function test_within_limit_denied_when_exceeded_after_trial(): void
    {
        [, $team] = $this->createSubscribedTeamWithUser(
            planFeatures: ['max_open_ballots' => 2],
            trialEndsAt: now()->subDay(),
        );

        $this->assertTrue(SubscriptionEntitlementGate::withinLimit($team, 'max_open_ballots', 2));
        $this->assertFalse(SubscriptionEntitlementGate::withinLimit($team, 'max_open_ballots', 3));
    }

    /**
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $planFeatures
     * @return array{0: User, 1: Team}
     */
    protected function createSubscribedTeamWithUser(
        array $permissions = ['vote_resolutions', 'create_resolutions'],
        array $planFeatures = ['features' => ['voting']],
        ?Carbon $trialEndsAt = null,
    ): array {
        $this->seedPermissions();
        $roleId = $this->createRoleWithPermissions('member', $permissions);

        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'user-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $team = Team::query()->create([
            'name' => 'Test Team',
            'user_id' => $user->id,
            'trial_ends_at' => $trialEndsAt,
        ]);

        $team->simulatePlanFeatures($planFeatures);
        $team->users()->attach($user);
        $user->update(['current_team_id' => $team->id]);

        DB::table('user_role')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'team_id' => $team->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user->fresh(), $team->fresh()];
    }
}
