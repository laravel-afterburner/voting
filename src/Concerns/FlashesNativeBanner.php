<?php

namespace Afterburner\Voting\Concerns;

trait FlashesNativeBanner
{
    protected function flashSuccessBanner(string $message): void
    {
        session()->flash('flash.banner', $message);
        session()->flash('flash.bannerStyle', 'success');
    }

    protected function flashDangerBanner(string $message): void
    {
        session()->flash('flash.banner', $message);
        session()->flash('flash.bannerStyle', 'danger');
    }
}
