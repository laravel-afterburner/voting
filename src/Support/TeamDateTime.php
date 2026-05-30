<?php

namespace Afterburner\Voting\Support;

use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TeamDateTime
{
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

    public static function toDateTimeLocal(Team $team, mixed $dateTime, ?string $userTimezone = null): ?string
    {
        if (! $dateTime) {
            return null;
        }

        $userTimezone ??= self::userTimezone();

        if (method_exists($team, 'toDateTimeLocal')) {
            return $team->toDateTimeLocal($dateTime, $userTimezone);
        }

        $carbon = $dateTime instanceof Carbon ? $dateTime->copy() : Carbon::parse($dateTime);

        return $carbon->setTimezone(self::teamTimezone($team))->format('Y-m-d\TH:i');
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

        return Carbon::parse($value, self::teamTimezone($team))->utc();
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

    public static function format(Team $team, mixed $dateTime, string $format = 'M j, Y g:i A T'): ?string
    {
        return self::toTeamTimezone($team, $dateTime)?->format($format);
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

        return format_date_superscript($carbon, $includeTime ? 'datetime' : 'date');
    }
}
