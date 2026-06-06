<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;

final class TeamPermissionGate
{
    /**
     * @param  list<string>  $permissions
     */
    public static function allowsAny(User $user, int $teamId, array $permissions): bool
    {
        if (class_exists(PermissionCatalog::class)) {
            return PermissionCatalog::allowsAny($user, $teamId, $permissions);
        }

        if (self::ownsTeam($user, $teamId)) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission, $teamId)) {
                return true;
            }
        }

        return false;
    }

    public static function allows(User $user, int $teamId, string $permission): bool
    {
        return self::allowsAny($user, $teamId, [$permission]);
    }

    public static function ownsTeam(User $user, int $teamId): bool
    {
        if (method_exists($user, 'ownsTeamById')) {
            return $user->ownsTeamById($teamId);
        }

        $teamModel = config('afterburner.team_model', Team::class);

        return $teamModel::query()
            ->whereKey($teamId)
            ->where('user_id', $user->getKey())
            ->exists();
    }
}
