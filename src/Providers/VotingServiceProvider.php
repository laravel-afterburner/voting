<?php

namespace Afterburner\Voting\Providers;

use Afterburner\Playbook\Support\Playbook;
use Afterburner\Subscriptions\Support\SubscriptionPackageFeatures;
use Afterburner\Voting\Console\Commands\InstallCommand;
use Afterburner\Voting\Console\Commands\ProcessScheduledBallotsCommand;
use Afterburner\Voting\Contracts\CustomElectorateResolver;
use Afterburner\Voting\Contracts\ProxyGrantResolver;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Database\Seeders\VotingPermissionsSeeder;
use Afterburner\Voting\Events\BallotPublished;
use Afterburner\Voting\Listeners\SendBallotPublishedVoterNotifications;
use Afterburner\Voting\Livewire\Ballots\BallotDocuments;
use Afterburner\Voting\Livewire\Ballots\BallotVoteForm;
use Afterburner\Voting\Livewire\Ballots\BulkVoteForm;
use Afterburner\Voting\Livewire\Ballots\Create;
use Afterburner\Voting\Livewire\Ballots\Index;
use Afterburner\Voting\Livewire\Ballots\Results;
use Afterburner\Voting\Livewire\Ballots\Show;
use Afterburner\Voting\Livewire\Ballots\VoteForm;
use Afterburner\Voting\Livewire\Proxies\Manager as ProxyManager;
use Afterburner\Voting\Livewire\Settings\VotingSettings;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\ProxyVote;
use Afterburner\Voting\Policies\BallotPolicy;
use Afterburner\Voting\Policies\ProxyVotePolicy;
use Afterburner\Voting\Support\DocumentsIntegration;
use Afterburner\Voting\Support\SubscriptionEntitlementGate;
use Afterburner\Voting\Support\TeamVotingSettings;
use Afterburner\Voting\Support\VotingPermissions;
use App\Models\Team;
use App\Support\Audit\AuditCategories;
use App\Support\DashboardSections;
use App\Support\Navigation;
use App\Support\NavigationActive;
use App\Support\PackageSeederRegistry;
use App\Support\SystemSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class VotingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! class_exists(Team::class)) {
            return;
        }

        $this->mergeConfigFrom(
            __DIR__.'/../../config/afterburner-voting.php',
            'afterburner-voting'
        );

        $this->app->singleton(VoterEligibilityResolver::class, function (Application $app) {
            $class = config('afterburner-voting.eligibility_resolver');

            if (! is_string($class) || ! class_exists($class)) {
                throw new \InvalidArgumentException('Invalid AFTERBURNER_VOTING_ELIGIBILITY_RESOLVER configuration.');
            }

            if (! is_subclass_of($class, VoterEligibilityResolver::class)) {
                throw new \InvalidArgumentException("{$class} must implement ".VoterEligibilityResolver::class);
            }

            return $app->make($class);
        });

        $this->registerProxyGrantResolver();

        $this->validateCustomElectorateResolver();
    }

    protected function registerProxyGrantResolver(): void
    {
        $class = config('afterburner-voting.proxy_grant_resolver');

        if ($class === null || $class === '') {
            return;
        }

        if (! is_string($class) || ! class_exists($class)) {
            throw new \InvalidArgumentException('Invalid AFTERBURNER_VOTING_PROXY_GRANT_RESOLVER configuration.');
        }

        if (! is_subclass_of($class, ProxyGrantResolver::class)) {
            throw new \InvalidArgumentException("{$class} must implement ".ProxyGrantResolver::class);
        }

        $this->app->singleton(ProxyGrantResolver::class, fn (Application $app) => $app->make($class));
    }

    protected function validateCustomElectorateResolver(): void
    {
        $class = config('afterburner-voting.custom_electorate_resolver');

        if ($class === null || $class === '') {
            return;
        }

        if (! is_string($class) || ! class_exists($class)) {
            throw new \InvalidArgumentException('Invalid AFTERBURNER_VOTING_CUSTOM_ELECTORATE_RESOLVER configuration.');
        }

        if (! is_subclass_of($class, CustomElectorateResolver::class)) {
            throw new \InvalidArgumentException("{$class} must implement ".CustomElectorateResolver::class);
        }
    }

    public function boot(): void
    {
        if (! class_exists(Team::class)) {
            return;
        }

        if (! config('afterburner-voting.enabled', true)) {
            return;
        }

        $this->publishes([
            __DIR__.'/../../config/afterburner-voting.php' => config_path('afterburner-voting.php'),
        ], 'afterburner-voting-config');

        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'afterburner-voting-migrations');

        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/afterburner-voting'),
        ], 'afterburner-voting-assets');

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'afterburner-voting');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

        $this->registerLivewireComponents();
        $this->registerPolicies();
        $this->registerAuditSkipRoutes();
        $this->registerAuditCategories();
        $this->registerNavigation();
        $this->registerDashboardSections();
        $this->registerPlaybook();
        $this->registerSystemSettings();
        $this->registerEventListeners();
        $this->registerSchedule();
        $this->registerPackageSeeder();
        $this->registerSubscriptionPackageFeatures();

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                ProcessScheduledBallotsCommand::class,
            ]);
        }
    }

    protected function registerSchedule(): void
    {
        if (! config('afterburner-voting.schedule_transitions', true)) {
            return;
        }

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('afterburner:voting:process-scheduled')->everyMinute();
        });
    }

    protected function registerLivewireComponents(): void
    {
        Livewire::component('voting.index', Index::class);
        Livewire::component('voting.show', Show::class);
        Livewire::component('voting.vote-form', VoteForm::class);
        Livewire::component('voting.bulk-vote-form', BulkVoteForm::class);
        Livewire::component('voting.ballot-vote-form', BallotVoteForm::class);
        Livewire::component('voting.create', Create::class);
        Livewire::component('voting.results', Results::class);

        if (DocumentsIntegration::isAvailable()) {
            Livewire::component('voting.ballot-documents', BallotDocuments::class);
        }

        Livewire::component('voting.settings.voting-settings', VotingSettings::class);

        if (config('afterburner-voting.proxy_grant_resolver')) {
            Livewire::component('voting.proxy-manager', ProxyManager::class);
        }
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Ballot::class, BallotPolicy::class);
        Gate::policy(ProxyVote::class, ProxyVotePolicy::class);
    }

    protected function registerAuditSkipRoutes(): void
    {
        if (! config()->has('audit.skip_routes')) {
            return;
        }

        $skipRoutes = config('afterburner-voting.audit.skip_routes', []);

        config([
            'audit.skip_routes' => array_values(array_unique(array_merge(
                config('audit.skip_routes', []),
                $skipRoutes
            ))),
        ]);
    }

    protected function registerAuditCategories(): void
    {
        if (! class_exists(AuditCategories::class)) {
            return;
        }

        AuditCategories::register([
            'voting' => 'Voting',
        ]);
    }

    protected function registerNavigation(): void
    {
        if (! class_exists(Navigation::class)) {
            return;
        }

        $proxyNavItem = $this->proxyNavigationItem();

        $item = [
            'label' => 'Voting',
            'route' => 'teams.ballots.index',
            'route_params' => function () {
                $user = auth()->user();
                if (! $user || ! $user->currentTeam) {
                    return [];
                }

                return ['team' => $user->currentTeam->id];
            },
            'icon' => 'ticket',
            'order' => 25,
            'permission' => function ($user) use ($proxyNavItem) {
                if (! $user || ! $user->currentTeam) {
                    return false;
                }

                if ($user->currentTeam && VotingPermissions::canAccessModule($user, $user->currentTeam)) {
                    return true;
                }

                return $proxyNavItem !== null
                    && isset($proxyNavItem['permission'])
                    && is_callable($proxyNavItem['permission'])
                    && $proxyNavItem['permission']($user);
            },
            'active' => function () use ($proxyNavItem) {
                $routes = ['teams.ballots.*'];

                if ($proxyNavItem !== null) {
                    $routes[] = 'teams.voting.proxies';
                }

                return NavigationActive::routeIs(...$routes);
            },
        ];

        if ($proxyNavItem !== null) {
            $item['children'] = [
                [
                    'label' => 'Ballots',
                    'route' => 'teams.ballots.index',
                    'route_params' => function () {
                        $user = auth()->user();
                        if (! $user || ! $user->currentTeam) {
                            return [];
                        }

                        return ['team' => $user->currentTeam->id];
                    },
                    'permission' => fn ($user) => $user?->currentTeam
                        && VotingPermissions::canViewSection($user, $user->currentTeam, VotingPermissions::SECTION_BALLOTS),
                    'active' => fn () => NavigationActive::routeIs('teams.ballots.*'),
                ],
                $proxyNavItem,
            ];
        }

        Navigation::register($item);
    }

    protected function registerDashboardSections(): void
    {
        if (! class_exists(DashboardSections::class)) {
            return;
        }

        DashboardSections::register([
            'key' => 'kpi.ballots',
            'label' => 'Open ballots',
            'description' => 'Ballots currently open for voting.',
            'group' => 'Overview metrics',
            'group_order' => 10,
            'order' => 40,
        ]);
    }

    protected function registerPlaybook(): void
    {
        if (! class_exists(Playbook::class)) {
            return;
        }

        Playbook::register([
            'key' => 'voting',
            'label' => 'Voting',
            'order' => 25,
            'path' => __DIR__.'/../../playbook',
            'enabled' => fn () => config('afterburner-voting.enabled', true),
            'permission' => fn ($user) => $user?->can('viewAny', Ballot::class) ?? false,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function proxyNavigationItem(): ?array
    {
        if (empty(config('afterburner-voting.proxy_grant_resolver'))) {
            return null;
        }

        return [
            'label' => 'Proxy votes',
            'route' => 'teams.voting.proxies',
            'route_params' => function () {
                $user = auth()->user();
                if (! $user || ! $user->currentTeam) {
                    return [];
                }

                return ['team' => $user->currentTeam->id];
            },
            'permission' => function ($user) {
                if (! $user || ! $user->currentTeam) {
                    return false;
                }

                if (! TeamVotingSettings::allowProxyVotesForTeam($user->currentTeam)) {
                    return false;
                }

                if (! SubscriptionEntitlementGate::allows($user->currentTeam)) {
                    return false;
                }

                if (! app()->bound(ProxyGrantResolver::class)) {
                    return false;
                }

                return VotingPermissions::canViewSection($user, $user->currentTeam, VotingPermissions::SECTION_PROXIES)
                    && app(ProxyGrantResolver::class)->userCanAccess($user, $user->currentTeam);
            },
            'active' => fn () => NavigationActive::routeIs('teams.voting.proxies'),
        ];
    }

    protected function registerSystemSettings(): void
    {
        if (! class_exists(SystemSettings::class)) {
            return;
        }

        if (! config('afterburner-voting.enabled', true)) {
            return;
        }

        SystemSettings::register([
            'key' => 'voting',
            'order' => 20,
            'component' => 'voting.settings.voting-settings',
            'params' => fn ($team) => ['team' => $team],
            'permission' => function ($user) {
                if (! $user || ! $user->currentTeam) {
                    return false;
                }

                if (! SubscriptionEntitlementGate::allows($user->currentTeam)) {
                    return false;
                }

                return $user->can('update', $user->currentTeam);
            },
        ]);
    }

    protected function registerEventListeners(): void
    {
        Event::listen(
            BallotPublished::class,
            SendBallotPublishedVoterNotifications::class
        );
    }

    protected function registerPackageSeeder(): void
    {
        if (class_exists(PackageSeederRegistry::class)) {
            PackageSeederRegistry::register(VotingPermissionsSeeder::class);
        }
    }

    protected function registerSubscriptionPackageFeatures(): void
    {
        if (! class_exists(SubscriptionPackageFeatures::class)) {
            return;
        }

        SubscriptionPackageFeatures::register('voting', 'Voting', [
            'Electronic ballots',
            'Proxy voting',
            'Results & reporting',
        ]);
    }
}
