<?php

namespace Afterburner\Voting\Support;

use Afterburner\Subscriptions\Concerns\HasSubscriptions;
use Afterburner\Subscriptions\Support\SubscriptionEntitlementGate as CoreSubscriptionEntitlementGate;
use Illuminate\Database\Eloquent\Model;

final class SubscriptionEntitlementGate
{
    public const FEATURE_SLUG = 'voting';

    public static function allows(Model $team): bool
    {
        if (self::usesCoreSubscriptionGate($team)) {
            return CoreSubscriptionEntitlementGate::allows($team, self::FEATURE_SLUG);
        }

        if (! self::subscriptionsEnforcementActive($team)) {
            return true;
        }

        if (self::teamOnGenericTrial($team)) {
            return true;
        }

        if (method_exists($team, 'hasActiveSubscription') && ! $team->hasActiveSubscription()) {
            return false;
        }

        return $team->hasEntitlement(self::FEATURE_SLUG);
    }

    public static function withinLimit(Model $team, string $key, int $current): bool
    {
        if (self::usesCoreSubscriptionGate($team)) {
            return CoreSubscriptionEntitlementGate::withinLimit($team, $key, $current);
        }

        if (! self::subscriptionsEnforcementActive($team)) {
            return true;
        }

        if (self::teamOnGenericTrial($team)) {
            return true;
        }

        if (method_exists($team, 'hasActiveSubscription') && ! $team->hasActiveSubscription()) {
            return false;
        }

        return $team->withinEntitlementLimit($key, $current);
    }

    protected static function usesCoreSubscriptionGate(Model $team): bool
    {
        return in_array(HasSubscriptions::class, class_uses_recursive($team), true);
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
        return $team->onGenericTrial();
    }
}
