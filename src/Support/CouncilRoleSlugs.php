<?php

namespace Afterburner\Voting\Support;

final class CouncilRoleSlugs
{
    /**
     * @return array<int, string>
     */
    public static function resolve(): array
    {
        $resolver = config('afterburner-voting.council_role_resolver');

        if (is_string($resolver) && class_exists($resolver) && method_exists($resolver, 'slugs')) {
            return $resolver::slugs();
        }

        return config('afterburner-voting.council_role_slugs', []);
    }
}
