<?php

namespace Afterburner\Voting\Support;

use Afterburner\Documents\Models\Document;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\ProxyVote;
use App\Models\Team;
use App\Models\User;

class VotingAuditLogger
{
    public const CATEGORY = 'voting';

    public static function ballotCreated(Ballot $ballot, User $user): void
    {
        self::log(
            'ballot.created',
            $ballot,
            "{$user->name} created ballot \"{$ballot->title}\".",
            [
                'ballot_id' => $ballot->id,
                'title' => $ballot->title,
                'type' => $ballot->type->value,
                'status' => $ballot->status->value,
            ],
            $ballot->team_id,
            $user,
        );
    }

    /**
     * @param  array<string, array{before: mixed, after: mixed}>  $fieldChanges
     */
    public static function ballotUpdated(Ballot $ballot, User $user, array $fieldChanges): void
    {
        if ($fieldChanges === []) {
            return;
        }

        self::log(
            'ballot.updated',
            $ballot,
            "{$user->name} updated ballot \"{$ballot->title}\".",
            array_merge(
                ['ballot_id' => $ballot->id, 'title' => $ballot->title],
                $fieldChanges,
            ),
            $ballot->team_id,
            $user,
        );
    }

    public static function ballotDeleted(Ballot $ballot, User $user, int $responsesCount = 0): void
    {
        self::log(
            'ballot.deleted',
            $ballot,
            "{$user->name} deleted ballot \"{$ballot->title}\".",
            [
                'ballot_id' => $ballot->id,
                'title' => $ballot->title,
                'status' => $ballot->status->value,
                'responses_count' => $responsesCount,
            ],
            $ballot->team_id,
            $user,
        );
    }

    public static function ballotDocumentAttached(Ballot $ballot, Document $document, User $user): void
    {
        self::log(
            'ballot.document.attached',
            $ballot,
            "{$user->name} attached \"{$document->name}\" to ballot \"{$ballot->title}\".",
            [
                'ballot_id' => $ballot->id,
                'document_id' => $document->id,
                'document_name' => $document->name,
            ],
            $ballot->team_id,
            $user,
        );
    }

    public static function ballotDocumentDetached(Ballot $ballot, Document $document, User $user): void
    {
        self::log(
            'ballot.document.detached',
            $ballot,
            "{$user->name} removed \"{$document->name}\" from ballot \"{$ballot->title}\".",
            [
                'ballot_id' => $ballot->id,
                'document_id' => $document->id,
                'document_name' => $document->name,
            ],
            $ballot->team_id,
            $user,
        );
    }

    public static function proxyCreated(ProxyVote $proxy, User $user): void
    {
        self::log(
            'proxy.created',
            $proxy,
            "{$user->name} recorded a proxy vote.",
            [
                'proxy_vote_id' => $proxy->id,
                'ballot_id' => $proxy->ballot_id,
            ],
            $proxy->ballot?->team_id,
            $user,
        );
    }

    public static function proxyRevoked(ProxyVote $proxy, User $user): void
    {
        self::log(
            'proxy.revoked',
            $proxy,
            "{$user->name} revoked a proxy vote.",
            ['proxy_vote_id' => $proxy->id, 'ballot_id' => $proxy->ballot_id],
            $proxy->ballot?->team_id,
            $user,
        );
    }

    public static function settingsUpdated(Team $team, User $user, array $changes): void
    {
        self::log(
            'voting.settings.updated',
            $team,
            "{$user->name} updated voting settings.",
            $changes,
            $team->id,
            $user,
            actionType: 'livewire',
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected static function log(
        string $eventName,
        object $auditable,
        string $summary,
        array $context,
        ?int $teamId,
        User $user,
        string $actionType = 'action_class',
    ): void {
        if (! class_exists(\App\Support\Audit\AuditLogger::class)) {
            return;
        }

        \App\Support\Audit\AuditLogger::log(
            category: self::CATEGORY,
            eventName: $eventName,
            auditable: $auditable,
            changes: \App\Support\Audit\AuditLogger::changesWithSummary($summary, context: $context),
            metadata: ['actor_user_id' => $user->id],
            teamId: $teamId,
            actionType: $actionType,
        );
    }
}
