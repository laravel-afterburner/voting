<?php

namespace Afterburner\Voting\Tests\Feature;

use Afterburner\Voting\Providers\VotingServiceProvider;
use Afterburner\Voting\Tests\TestCase;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\Gate;

class SystemSettingsRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SystemSettings::clear();
        (new VotingServiceProvider($this->app))->boot();
    }

    protected function tearDown(): void
    {
        SystemSettings::clear();

        parent::tearDown();
    }

    public function test_registers_voting_section_on_system_settings(): void
    {
        [$user, $team] = $this->createTeamWithUser(['create_resolutions']);

        Gate::before(fn () => true);
        $this->actingAs($user);

        $sections = SystemSettings::sections();

        $this->assertCount(1, $sections);
        $this->assertSame('voting', $sections->first()['key']);
        $this->assertSame('voting.settings.voting-settings', $sections->first()['component']);
        $this->assertSame(20, $sections->first()['order']);
        $this->assertSame(['team' => $team], value($sections->first()['params'], $team));
    }

    public function test_does_not_register_voting_section_when_disabled(): void
    {
        SystemSettings::clear();
        config(['afterburner-voting.enabled' => false]);
        (new VotingServiceProvider($this->app))->boot();

        $this->assertCount(0, SystemSettings::sections());
    }
}
