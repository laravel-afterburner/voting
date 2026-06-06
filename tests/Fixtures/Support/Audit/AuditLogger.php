<?php

namespace App\Support\Audit;

class AuditLogger
{
    public static function log(
        string $category,
        string $eventName,
        mixed $auditable,
        array $changes = [],
        array $metadata = [],
        ?int $teamId = null,
        ?string $actionType = null,
    ): void {
        // No-op in package tests.
    }

    /**
     * @param  array<string, array{before: mixed, after: mixed}>  $fieldChanges
     * @param  array<string, mixed>  $context
     */
    public static function changesWithSummary(
        string $summary,
        array $fieldChanges = [],
        array $context = [],
    ): array {
        return array_merge(['summary' => $summary], $context, ['fields' => $fieldChanges]);
    }
}
