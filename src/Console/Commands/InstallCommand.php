<?php

namespace Afterburner\Voting\Console\Commands;

use Afterburner\Voting\Database\Seeders\VotingPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'afterburner:voting:install';

    protected $description = 'Install the Afterburner Voting package';

    public function handle(): int
    {
        $this->info('Installing Afterburner Voting package...');

        $this->info('Publishing configuration...');
        $this->call('vendor:publish', [
            '--tag' => 'afterburner-voting-config',
            '--force' => true,
        ]);

        $this->info('Publishing views...');
        $this->call('vendor:publish', [
            '--tag' => 'afterburner-voting-assets',
            '--force' => true,
        ]);

        $this->info('Adding environment variables...');
        $this->addEnvironmentVariables();

        if ($this->confirm('Run migrations now?', true)) {
            $this->info('Running migrations...');
            $this->call('migrate');
        }

        if ($this->confirm('Seed voting permissions?', true)) {
            $this->info('Seeding voting permissions...');
            $seeder = new VotingPermissionsSeeder;
            $seeder->setCommand($this);
            $seeder->run();
        }

        $this->info('Installation complete!');
        $this->newLine();
        $this->comment('Next steps:');
        $this->comment('1. Add the HasVoting trait to App\\Models\\Team');
        $this->comment('2. For strata apps, implement a custom VoterEligibilityResolver');
        $this->comment('3. Visit /teams/{team}/ballots to start using voting');
        $this->comment('4. Configure team defaults in System Settings → Voting');
        $this->comment('Note: Voting migrations load automatically from the package.');

        return Command::SUCCESS;
    }

    protected function addEnvironmentVariables(): void
    {
        $envVars = [
            '',
            '# Afterburner Voting Configuration',
            'AFTERBURNER_VOTING_ENABLED=true',
            'AFTERBURNER_VOTING_DEFAULT_VISIBILITY=visible_after_close',
            'AFTERBURNER_VOTING_ALLOW_PROXY=true',
            '# AFTERBURNER_VOTING_CUSTOM_ELECTORATE_RESOLVER=',
        ];

        foreach (['.env', '.env.example'] as $file) {
            $path = base_path($file);
            if (! File::exists($path)) {
                continue;
            }

            $content = File::get($path);
            foreach ($envVars as $var) {
                if ($var && ! str_contains($content, explode('=', $var)[0])) {
                    File::append($path, "\n".$var);
                }
            }
        }
    }
}
