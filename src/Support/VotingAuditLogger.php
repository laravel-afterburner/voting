<?php

namespace Afterburner\Voting\Support;

use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\ProxyVote;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit\AuditLogger;

class VotingAuditLogger
{
    public const CATEGORY = 'voting';

    public static function ballotCreated(Ballot $ballot, User $user): void
    {
        AuditLogger::log(
            category: self::CATEGORY,
            eventName: 'ballot.created',
            auditable: $ballot,
            changes: AuditLogger::changesWithSummary(
                summary: "{$user->name} created ballot \"{$ballot->title}\".",
                context: [
                    'ballot_id' => $ballot->id,
                    'title' => $ballot->title,
                    'type' => $ballot->type->value,
                    'status' => $ballot->status->value,
                ],
            ),
            metadata: ['actor_user_id' => $user->id],
            teamId: $ballot->team_id,
            actionType: 'action_class',
        );
    }

    public static function ballotDeleted(Ballot $ballot, User $user): void
    {
        AuditLogger::log(
            category: self::CATEGORY,
            eventName: 'ballot.deleted',
            auditable: $ballot,
            changes: AuditLogger::changesWithSummary(
                summary: "{$user->name} deleted ballot \"{$ballot->title}\".",
                context: ['ballot_id' => $ballot->id, 'title' => $ballot->title],
            ),
            metadata: ['actor_user_id' => $user->id],
            teamId: $ballot->team_id,
            actionType: 'action_class',
        );
    }

    public static function proxyCreated(ProxyVote $proxy, User $user): void
    {
        AuditLogger::log(
            category: self::CATEGORY,
            eventName: 'proxy.created',
            auditable: $proxy,
            changes: AuditLogger::changesWithSummary(
                summary: "{$user->name} recorded a proxy vote.",
                context: [
                    'proxy_vote_id' => $proxy->id,
                    'ballot_id' => $proxy->ballot_id,
                ],
            ),
            metadata: ['actor_user_id' => $user->id],
            teamId: $proxy->ballot?->team_id,
            actionType: 'action_class',
        );
    }

    public static function proxyRevoked(ProxyVote $proxy, User $user): void
    {
        AuditLogger::log(
            category: self::CATEGORY,
            eventName: 'proxy.revoked',
            auditable: $proxy,
            changes: AuditLogger::changesWithSummary(
                summary: "{$user->name} revoked a proxy vote.",
                context: ['proxy_vote_id' => $proxy->id, 'ballot_id' => $proxy->ballot_id],
            ),
            metadata: ['actor_user_id' => $user->id],
            teamId: $proxy->ballot?->team_id,
            actionType: 'action_class',
        );
    }

    public static function settingsUpdated(Team $team, User $user, array $changes): void
    {
        AuditLogger::log(
            category: self::CATEGORY,
            eventName: 'voting.settings.updated',
            auditable: $team,
            changes: AuditLogger::changesWithSummary(
                summary: "{$user->name} updated voting settings.",
                context: $changes,
            ),
            metadata: ['actor_user_id' => $user->id],
            teamId: $team->id,
            actionType: 'livewire',
        );
    }
}
