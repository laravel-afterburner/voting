<?php

namespace App\Traits;

trait SimulatesSubscriptionEntitlements
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected static array $simulatedPlanFeaturesByTeamId = [];

    /**
     * @param  array<string, mixed>  $features
     */
    public function simulatePlanFeatures(array $features): static
    {
        if ($this->getKey() !== null) {
            static::$simulatedPlanFeaturesByTeamId[$this->getKey()] = $features;
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolvedPlanFeatures(): array
    {
        if ($this->getKey() !== null && isset(static::$simulatedPlanFeaturesByTeamId[$this->getKey()])) {
            return static::$simulatedPlanFeaturesByTeamId[$this->getKey()];
        }

        return [];
    }

    public static function clearSimulatedPlanFeatures(): void
    {
        static::$simulatedPlanFeaturesByTeamId = [];
    }

    public function onGenericTrial(): bool
    {
        $endsAt = $this->trial_ends_at ?? null;

        if ($endsAt === null) {
            return false;
        }

        return $endsAt->isFuture();
    }

    public function hasEntitlement(string $featureSlug): bool
    {
        $features = $this->resolvedPlanFeatures()['features'] ?? [];

        if (! is_array($features)) {
            return false;
        }

        return in_array($featureSlug, $features, true);
    }

    public function withinEntitlementLimit(string $key, int $current): bool
    {
        $limit = $this->resolvedPlanFeatures()[$key] ?? null;

        if ($limit === null || $limit === '') {
            return true;
        }

        return $current <= (int) $limit;
    }
}
