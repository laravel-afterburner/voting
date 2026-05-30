<?php

namespace Afterburner\Voting\Support;

class PageHeader
{
    public static function make(string $section, ?string $action = null, ?string $detail = null): string
    {
        if ($action !== null && $detail !== null) {
            return "{$section} - {$action} {$detail}";
        }

        if ($detail !== null) {
            return "{$section} - {$detail}";
        }

        if ($action !== null) {
            return "{$section} - {$action}";
        }

        return $section;
    }
}
