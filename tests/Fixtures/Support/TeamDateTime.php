<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TeamDateTime
{
    public const CALENDAR_DISPLAY_TEAM = 'team';

    public const CALENDAR_DISPLAY_USER = 'user';

    public static function userTimezone(): ?string
    {
        $user = Auth::user();

        if ($user instanceof User && ! empty($user->timezone)) {
            return $user->timezone;
        }

        return request()->cookie('timezone');
    }

    public static function teamTimezone(Team $team): string
    {
        if (method_exists($team, 'getTimezone')) {
            return $team->getTimezone();
        }

        return $team->timezone ?? config('app.timezone', 'UTC');
    }

    /**
     * Short label for ballot schedule hints (e.g. PDT, PST) instead of IANA identifiers.
     */
    public static function scheduleTimezoneLabel(Team $team, ?Carbon $at = null): string
    {
        $timezone = self::teamTimezone($team);

        if ($timezone === 'UTC') {
            return 'UTC';
        }

        try {
            $at ??= Carbon::now('UTC');
            $localized = $at->copy()->setTimezone($timezone);
            $abbreviation = $localized->format('T');

            if (filled($abbreviation) && ! str_contains($abbreviation, '/')) {
                return $abbreviation;
            }
        } catch (\Exception) {
        }

        return self::regionalTimezoneLabel($timezone);
    }

    protected static function regionalTimezoneLabel(string $timezone): string
    {
        return match ($timezone) {
            'America/Vancouver', 'America/Los_Angeles', 'America/Tijuana', 'America/Whitehorse' => 'Pacific',
            'America/Edmonton', 'America/Denver', 'America/Phoenix' => 'Mountain',
            'America/Winnipeg', 'America/Chicago', 'America/Mexico_City' => 'Central',
            'America/Toronto', 'America/New_York', 'America/Montreal' => 'Eastern',
            'America/Halifax', 'America/Moncton' => 'Atlantic',
            'America/St_Johns' => 'Newfoundland',
            default => $timezone,
        };
    }

    public static function toDateTimeLocal(Team $team, mixed $dateTime, ?string $userTimezone = null): ?string
    {
        if (! $dateTime) {
            return null;
        }

        $userTimezone ??= self::userTimezone();

        if (method_exists($team, 'toDateTimeLocal')) {
            return $team->toDateTimeLocal($dateTime, $userTimezone);
        }

        $teamTimezone = self::teamTimezone($team);
        $carbon = ($dateTime instanceof Carbon ? $dateTime->copy() : Carbon::parse($dateTime))->utc();
        $teamCarbon = $carbon->setTimezone($teamTimezone);

        if ($userTimezone && $userTimezone !== $teamTimezone) {
            return $teamCarbon->copy()->setTimezone($userTimezone)->format('Y-m-d\TH:i');
        }

        return $teamCarbon->format('Y-m-d\TH:i');
    }

    public static function fromDateTimeLocal(Team $team, ?string $value, ?string $userTimezone = null): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        $userTimezone ??= self::userTimezone();

        if (method_exists($team, 'fromDateTimeLocal')) {
            return $team->fromDateTimeLocal($value, $userTimezone);
        }

        $teamTimezone = self::teamTimezone($team);

        if ($userTimezone && $userTimezone !== $teamTimezone) {
            return Carbon::parse($value, $userTimezone)->utc();
        }

        return Carbon::parse($value, $teamTimezone)->utc();
    }

    /**
     * Timezone label for datetime-local inputs (browser interprets values in the user's local timezone).
     */
    public static function datetimeLocalTimezone(Team $team): string
    {
        return self::userTimezone() ?? self::teamTimezone($team);
    }

    /**
     * Team timezone hint when it differs from the datetime-local input timezone.
     */
    public static function datetimeLocalTeamTimezoneHint(Team $team): ?string
    {
        $userTimezone = self::userTimezone();
        $teamTimezone = self::teamTimezone($team);

        if ($userTimezone && $userTimezone !== $teamTimezone) {
            return $teamTimezone;
        }

        return null;
    }

    public static function canChooseCalendarDisplayTimezone(Team $team): bool
    {
        $userTimezone = self::userTimezone();

        return $userTimezone !== null && $userTimezone !== self::teamTimezone($team);
    }

    public static function defaultCalendarDisplayMode(Team $team): string
    {
        return self::canChooseCalendarDisplayTimezone($team)
            ? self::CALENDAR_DISPLAY_USER
            : self::CALENDAR_DISPLAY_TEAM;
    }

    public static function resolveCalendarDisplayTimezone(Team $team, string $mode): string
    {
        if ($mode === self::CALENDAR_DISPLAY_USER) {
            return self::userTimezone() ?? self::teamTimezone($team);
        }

        return self::teamTimezone($team);
    }

    public static function toTeamTimezone(Team $team, mixed $dateTime): ?Carbon
    {
        if (! $dateTime) {
            return null;
        }

        if (method_exists($team, 'toTeamTimezone')) {
            return $team->toTeamTimezone($dateTime);
        }

        $carbon = $dateTime instanceof Carbon ? $dateTime->copy() : Carbon::parse($dateTime);

        return $carbon->setTimezone(self::teamTimezone($team));
    }

    public static function format(Team $team, mixed $dateTime, string $format = 'M j, Y g:i A (T)'): ?string
    {
        return self::toTeamTimezone($team, $dateTime)?->format($format);
    }

    /**
     * Format a time with timezone abbreviation, e.g. "2:30 PM (PDT)".
     */
    public static function formatTime(Carbon $carbon): string
    {
        return $carbon->format('g:i A').' ('.$carbon->format('T').')';
    }

    /**
     * Format a time range with a single timezone suffix, e.g. "9:00 AM – 12:00 PM (PDT)".
     */
    public static function formatTimeRange(Carbon $startsAt, Carbon $endsAt): string
    {
        if ($startsAt->eq($endsAt)) {
            return self::formatTime($startsAt);
        }

        return $startsAt->format('g:i A').' – '.$endsAt->format('g:i A').' ('.$startsAt->format('T').')';
    }

    /**
     * Format a Carbon already in the intended display timezone.
     * Returns HTML — use with {!! !!} in Blade.
     */
    public static function formatDisplayCarbon(Carbon $carbon, bool $includeTime = true): string
    {
        $formatted = format_date_superscript($carbon, $includeTime ? 'datetime' : 'date');

        if ($includeTime) {
            $formatted .= ' ('.$carbon->format('T').')';
        }

        return $formatted;
    }

    /**
     * Format a calendar entry schedule for HTML display.
     * Returns HTML — use with {!! !!} in Blade.
     */
    public static function formatCalendarEntrySchedule(Carbon $startsAt, Carbon $endsAt, bool $allDay): string
    {
        if ($allDay) {
            if ($startsAt->isSameDay($endsAt)) {
                return 'All day · '.format_date_superscript($startsAt, 'date');
            }

            return 'All day · '.format_date_superscript($startsAt, 'date').' – '.format_date_superscript($endsAt, 'date');
        }

        if ($startsAt->isSameDay($endsAt)) {
            return self::formatTimeRange($startsAt, $endsAt).' · '.format_date_superscript($startsAt, 'date');
        }

        return self::formatDisplayCarbon($startsAt).' – '.self::formatDisplayCarbon($endsAt);
    }

    /**
     * Format a date for HTML display with ordinal superscript and 12-hour time.
     * Returns HTML — use with {!! !!} in Blade.
     */
    public static function formatDisplay(Team $team, mixed $dateTime, bool $includeTime = true): ?string
    {
        $carbon = self::toTeamTimezone($team, $dateTime);

        if (! $carbon) {
            return null;
        }

        return self::formatDisplayCarbon($carbon, $includeTime);
    }
}
