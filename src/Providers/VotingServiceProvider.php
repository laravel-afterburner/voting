<?php

namespace Afterburner\Voting\Providers;

use Afterburner\Voting\Console\Commands\InstallCommand;
use Afterburner\Voting\Console\Commands\ProcessScheduledBallotsCommand;
use Afterburner\Voting\Contracts\CustomElectorateResolver;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Events\BallotPublished;
use Afterburner\Voting\Listeners\SendBallotPublishedVoterNotifications;
use Afterburner\Voting\Livewire\Ballots\BallotDocuments;
use Afterburner\Voting\Livewire\Ballots\Create;
use Afterburner\Voting\Livewire\Ballots\Index;
use Afterburner\Voting\Livewire\Ballots\Results;
use Afterburner\Voting\Livewire\Ballots\Show;
use Afterburner\Voting\Livewire\Ballots\VoteForm;
use Afterburner\Voting\Livewire\Settings\VotingSettings;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\ProxyVote;
use Afterburner\Voting\Policies\BallotPolicy;
use Afterburner\Voting\Policies\ProxyVotePolicy;
use Afterburner\Voting\Support\DocumentsIntegration;
use App\Models\Team;
use App\Support\Navigation;
use App\Support\TeamNavigation;
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

        $this->validateCustomElectorateResolver();
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
        $this->registerNavigation();
        $this->registerTeamNavigation();
        $this->registerEventListeners();
        $this->registerSchedule();

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
        Livewire::component('voting.create', Create::class);
        Livewire::component('voting.results', Results::class);

        if (DocumentsIntegration::isAvailable()) {
            Livewire::component('voting.ballot-documents', BallotDocuments::class);
        }

        Livewire::component('voting.settings.voting-settings', VotingSettings::class);
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

    protected function registerNavigation(): void
    {
        if (! class_exists(Navigation::class)) {
            return;
        }

        Navigation::register([
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
            'permission' => function ($user) {
                if (! $user || ! $user->currentTeam) {
                    return false;
                }

                return $user->can('viewAny', Ballot::class);
            },
            'active' => function () {
                return request()->routeIs('teams.ballots.*');
            },
        ]);
    }

    protected function registerTeamNavigation(): void
    {
        if (! class_exists(TeamNavigation::class)) {
            return;
        }

        TeamNavigation::register([
            'label' => 'Voting Settings',
            'route' => 'teams.voting-settings',
            'route_params' => function () {
                $user = auth()->user();
                if (! $user || ! $user->currentTeam) {
                    return [];
                }

                return ['team' => $user->currentTeam->id];
            },
            'order' => 16,
            'permission' => function ($user) {
                if (! $user || ! $user->currentTeam) {
                    return false;
                }

                return $user->can('update', $user->currentTeam);
            },
            'active' => function () {
                return request()->routeIs('teams.voting-settings');
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
}
