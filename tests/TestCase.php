<?php

namespace Afterburner\Voting\Tests;

use Afterburner\Documents\Providers\DocumentsServiceProvider;
use Tests\Concerns\ConfiguresAfterburnerEntity;
use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Enums\BallotType;
use Afterburner\Voting\Enums\ElectorateType;
use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotOption;
use Afterburner\Voting\Providers\VotingServiceProvider;
use App\Models\Team;
use App\Models\User;
use Barryvdh\DomPDF\ServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use ConfiguresAfterburnerEntity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__.'/Fixtures/entity_helpers.php';

        config([
            'afterburner-voting.enabled' => true,
        ]);
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            LivewireServiceProvider::class,
            VotingServiceProvider::class,
        ];

        if (class_exists(DocumentsServiceProvider::class)) {
            $providers[] = DocumentsServiceProvider::class;
        }

        if (class_exists(ServiceProvider::class)) {
            $providers[] = ServiceProvider::class;
        }

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        static::applyAfterburnerEntityConfig($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('auth.guards.web.provider', 'users');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $documentsMigrations = dirname(__DIR__, 2).'/afterburner-documents/database/migrations';
        if (is_dir($documentsMigrations)) {
            $this->loadMigrationsFrom($documentsMigrations);
        }
    }

    protected function seedPermissions(): void
    {
        $now = now();
        $permissions = [
            ['name' => 'Vote Resolutions', 'slug' => 'vote_resolutions', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Create Resolutions', 'slug' => 'create_resolutions', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Manage Ballots', 'slug' => 'manage_ballots', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'View Ballot Results', 'slug' => 'view_ballot_results', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Manage Proxy Votes', 'slug' => 'manage_proxy_votes', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Export Ballot Results', 'slug' => 'export_ballot_results', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'View Documents', 'slug' => 'view_documents', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insert($permission);
        }
    }

    protected function createRoleWithPermissions(string $slug, array $permissionSlugs): int
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => ucfirst(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'hierarchy' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($permissionSlugs as $permissionSlug) {
            $permissionId = DB::table('permissions')->where('slug', $permissionSlug)->value('id');
            DB::table('role_permission')->insert([
                'role_slug' => $slug,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $roleId;
    }

    protected function createTeamWithUser(array $permissions = ['vote_resolutions', 'create_resolutions']): array
    {
        $this->seedPermissions();
        $roleId = $this->createRoleWithPermissions('member', $permissions);

        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $team = Team::query()->create([
            'name' => 'Test Team',
            'user_id' => $user->id,
        ]);

        $team->users()->attach($user);
        $user->update(['current_team_id' => $team->id]);

        DB::table('user_role')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'team_id' => $team->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $team];
    }

    protected function createAdditionalUser(Team $team, array $permissions = ['vote_resolutions'], string $email = 'voter@example.com'): User
    {
        $roleId = $this->createRoleWithPermissions('member_'.$email, $permissions);

        $user = User::query()->create([
            'name' => 'Extra User',
            'email' => $email,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $team->users()->attach($user);
        $user->update(['current_team_id' => $team->id]);

        DB::table('user_role')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'team_id' => $team->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    protected function createOpenBallot(Team $team, User $creator, array $overrides = []): Ballot
    {
        $ballot = Ballot::query()->create(array_merge([
            'team_id' => $team->id,
            'created_by_user_id' => $creator->id,
            'title' => 'Test Ballot',
            'description' => 'Test description',
            'type' => BallotType::Resolution,
            'status' => BallotStatus::Open,
            'electorate' => ElectorateType::AllMembers,
            'vote_visibility' => VoteVisibility::VisibleAfterClose,
            'opens_at' => now()->subHour(),
            'closes_at' => now()->addWeek(),
            'published_at' => now(),
        ], $overrides));

        BallotOption::query()->create(['ballot_id' => $ballot->id, 'label' => 'Yes', 'sort_order' => 0]);
        BallotOption::query()->create(['ballot_id' => $ballot->id, 'label' => 'No', 'sort_order' => 1]);

        return $ballot->fresh(['options']);
    }
}
