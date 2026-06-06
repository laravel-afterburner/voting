<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * User-facing entity labels from afterburner config (team, strata, company, etc.).
 */
final class EntityLabel
{
    public static function singular(): string
    {
        return (string) config('afterburner.entity_label', 'team');
    }

    public static function singularTitle(): string
    {
        return Str::title(static::singular());
    }

    /**
     * Plural display label — uses entity_url_slug so irregular plurals (e.g. strata) stay correct.
     */
    public static function pluralTitle(): string
    {
        $slug = config('afterburner.entity_url_slug');

        if (is_string($slug) && $slug !== '') {
            return Str::title($slug);
        }

        return Str::title(Str::plural(static::singular()));
    }

    public static function plural(): string
    {
        return Str::lower(static::pluralTitle());
    }

    /**
     * URL path segment for entity-scoped routes (e.g. strata, teams, companies).
     */
    public static function urlSlug(): string
    {
        $slug = config('afterburner.entity_url_slug');

        if (is_string($slug) && $slug !== '') {
            return $slug;
        }

        return Str::lower(Str::plural(static::singular()));
    }
}
