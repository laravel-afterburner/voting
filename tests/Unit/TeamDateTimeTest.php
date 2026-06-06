<?php

namespace Afterburner\Voting\Tests\Unit;

use App\Support\TeamDateTime;
use Afterburner\Voting\Tests\TestCase;
use Carbon\Carbon;

class TeamDateTimeTest extends TestCase
{
    public function test_from_and_to_datetime_local_use_team_timezone(): void
    {
        config(['app.timezone' => 'UTC']);

        [, $team] = $this->createTeamWithUser();
        $team->update(['timezone' => 'America/Vancouver']);

        $utc = Carbon::parse('2026-06-15 17:00:00', 'UTC');

        $local = TeamDateTime::toDateTimeLocal($team, $utc);
        $this->assertSame('2026-06-15T10:00', $local);

        $parsed = TeamDateTime::fromDateTimeLocal($team, '2026-06-15T10:00');
        $this->assertSame('2026-06-15 17:00:00', $parsed->utc()->format('Y-m-d H:i:s'));
    }

    public function test_schedule_timezone_label_uses_dst_abbreviation(): void
    {
        [, $team] = $this->createTeamWithUser();
        $team->update(['timezone' => 'America/Vancouver']);

        $summer = Carbon::parse('2026-07-01 12:00:00', 'UTC');
        $winter = Carbon::parse('2026-01-15 12:00:00', 'UTC');

        $this->assertSame('PDT', TeamDateTime::scheduleTimezoneLabel($team, $summer));
        $this->assertSame('PST', TeamDateTime::scheduleTimezoneLabel($team, $winter));
    }
}
