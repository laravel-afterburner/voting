<?php

namespace App\Support\Concerns;

use Afterburner\Subscriptions\Concerns\HasSubscriptions;
use Afterburner\Subscriptions\Support\SubscriptionEntitlementGate as CoreSubscriptionEntitlementGate;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared optional-subscriptions checks for package entitlement gates.
 *
 * Each package gate class uses this trait and defines FEATURE_SLUG.
 */
trait ChecksOptionalSubscriptionEntitlement
{
    protected static function allowsSubscriptionFeature(Model $team, string $featureSlug): bool
    {
        if (static::usesCoreSubscriptionGate($team)) {
            return CoreSubscriptionEntitlementGate::allows($team, $featureSlug);
        }

        if (! static::subscriptionsEnforcementActive($team)) {
            return true;
        }

        if (static::teamOnGenericTrial($team)) {
            return true;
        }

        if (method_exists($team, 'hasActiveSubscription') && ! $team->hasActiveSubscription()) {
            return false;
        }

        return $team->hasEntitlement($featureSlug);
    }

    protected static function withinSubscriptionLimit(Model $team, string $featureSlug, string $key, int $current): bool
    {
        if (static::usesCoreSubscriptionGate($team)) {
            return CoreSubscriptionEntitlementGate::withinLimit($team, $key, $current);
        }

        if (! static::subscriptionsEnforcementActive($team)) {
            return true;
        }

        if (static::teamOnGenericTrial($team)) {
            return true;
        }

        if (method_exists($team, 'hasActiveSubscription') && ! $team->hasActiveSubscription()) {
            return false;
        }

        return $team->withinEntitlementLimit($key, $current);
    }

    protected static function usesCoreSubscriptionGate(Model $team): bool
    {
        return class_exists(HasSubscriptions::class)
            && in_array(HasSubscriptions::class, class_uses_recursive($team), true);
    }

    protected static function subscriptionsEnforcementActive(Model $team): bool
    {
        if (! config('afterburner-subscriptions.enabled', false)) {
            return false;
        }

        return method_exists($team, 'hasEntitlement')
            && method_exists($team, 'onGenericTrial')
            && method_exists($team, 'withinEntitlementLimit');
    }

    protected static function teamOnGenericTrial(Model $team): bool
    {
        return method_exists($team, 'onGenericTrial') && $team->onGenericTrial();
    }
}
