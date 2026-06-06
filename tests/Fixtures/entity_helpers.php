<?php

use App\Support\EntityLabel;

if (! function_exists('entity_label')) {
    function entity_label(): string
    {
        return EntityLabel::singular();
    }
}

if (! function_exists('entity_title')) {
    function entity_title(): string
    {
        return EntityLabel::singularTitle();
    }
}

if (! function_exists('entity_plural')) {
    function entity_plural(): string
    {
        return EntityLabel::plural();
    }
}

if (! function_exists('entity_plural_title')) {
    function entity_plural_title(): string
    {
        return EntityLabel::pluralTitle();
    }
}

if (! function_exists('entity_url_slug')) {
    function entity_url_slug(): string
    {
        return EntityLabel::urlSlug();
    }
}

if (! function_exists('entity_path')) {
    function entity_path(string $path = ''): string
    {
        $slug = entity_url_slug();
        $path = ltrim($path, '/');

        return $path === '' ? '/'.$slug : '/'.$slug.'/'.$path;
    }
}
