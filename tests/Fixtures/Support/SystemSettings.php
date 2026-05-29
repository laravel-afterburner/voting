<?php

namespace App\Support;

use Illuminate\Support\Collection;

class SystemSettings
{
    protected static array $sections = [];

    /**
     * Register a system settings section.
     *
     * @param  array  $section  Section configuration
     */
    public static function register(array $section): void
    {
        self::$sections[] = array_merge([
            'order' => 100,
            'enabled' => true,
            'permission' => null,
        ], $section);
    }

    /**
     * Get all registered system settings sections, filtered and sorted.
     */
    public static function sections(): Collection
    {
        return collect(self::$sections)
            ->filter(function ($section) {
                if (isset($section['enabled']) && is_callable($section['enabled'])) {
                    if (! $section['enabled']()) {
                        return false;
                    }
                } elseif (isset($section['enabled']) && ! $section['enabled']) {
                    return false;
                }

                if (isset($section['permission']) && is_callable($section['permission'])) {
                    return $section['permission'](auth()->user());
                }

                return true;
            })
            ->sortBy('order')
            ->values();
    }

    /**
     * Clear all registered sections (useful for testing).
     */
    public static function clear(): void
    {
        self::$sections = [];
    }
}
