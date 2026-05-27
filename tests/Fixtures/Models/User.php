<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'current_team_id'];

    protected $hidden = ['password'];

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function belongsToTeam($team): bool
    {
        if ($team === null) {
            return false;
        }

        $teamId = is_object($team) ? $team->id : $team;

        return $this->teams()->where('teams.id', $teamId)->exists();
    }

    public function hasPermission(string $permissionSlug, ?int $teamId = null): bool
    {
        $teamId = $teamId ?? $this->currentTeam?->id;

        if (! $teamId) {
            return false;
        }

        return DB::table('user_role')
            ->join('roles', 'user_role.role_id', '=', 'roles.id')
            ->join('role_permission', 'roles.slug', '=', 'role_permission.role_slug')
            ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
            ->where('user_role.user_id', $this->id)
            ->where('user_role.team_id', $teamId)
            ->where('permissions.slug', $permissionSlug)
            ->exists();
    }
}
