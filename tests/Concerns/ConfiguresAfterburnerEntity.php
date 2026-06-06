<?php

namespace Tests\Concerns;

trait ConfiguresAfterburnerEntity
{
    public static function applyAfterburnerEntityConfig(
        mixed $app,
        string $label = 'team',
        ?string $urlSlug = null,
    ): void {
        $app['config']->set('afterburner.entity_label', $label);
        $app['config']->set(
            'afterburner.entity_url_slug',
            $urlSlug ?? ($label === 'team' ? 'teams' : $label),
        );
    }

    protected function configureAfterburnerEntity(
        string $label = 'team',
        ?string $urlSlug = null,
    ): void {
        static::applyAfterburnerEntityConfig($this->app, $label, $urlSlug);
    }
}
