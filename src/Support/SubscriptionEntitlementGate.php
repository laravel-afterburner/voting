<?php

namespace Afterburner\Voting\Support;

use App\Support\Concerns\ChecksOptionalSubscriptionEntitlement;
use Illuminate\Database\Eloquent\Model;

final class SubscriptionEntitlementGate
{
    use ChecksOptionalSubscriptionEntitlement;

    public const FEATURE_SLUG = 'voting';

    public static function allows(Model $team): bool
    {
        return static::allowsSubscriptionFeature($team, static::FEATURE_SLUG);
    }

    public static function withinLimit(Model $team, string $key, int $current): bool
    {
        return static::withinSubscriptionLimit($team, static::FEATURE_SLUG, $key, $current);
    }
}
