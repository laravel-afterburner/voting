<?php

namespace Afterburner\Voting\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VotingPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permissions')) {
            if (isset($this->command)) {
                $this->command->error('Permissions table does not exist. Please ensure your database migrations are up to date.');
            }

            return;
        }

        $now = Carbon::now();

        $permissions = [
            [
                'name' => 'Manage Ballots',
                'slug' => 'manage_ballots',
                'description' => 'Full admin: edit others drafts, cancel, force-close',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'View Ballot Results',
                'slug' => 'view_ballot_results',
                'description' => 'View ballot results based on visibility rules',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Manage Proxy Votes',
                'slug' => 'manage_proxy_votes',
                'description' => 'Grant and revoke proxy votes',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Export Ballot Results',
                'slug' => 'export_ballot_results',
                'description' => 'Download CSV or PDF of ballot results',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $insertedPermissionIds = [];
        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore($permission);
            $permissionRecord = DB::table('permissions')
                ->where('slug', $permission['slug'])
                ->first();
            if ($permissionRecord) {
                $insertedPermissionIds[] = $permissionRecord->id;
            }
        }

        if (! empty($insertedPermissionIds) && DB::getSchemaBuilder()->hasTable('role_permission')) {
            $assignedCount = $this->assignPermissionsToTeamOwners($insertedPermissionIds, $permissions, $now);

            if (isset($this->command) && $assignedCount > 0) {
                $this->command->info("✓ Permissions assigned to {$assignedCount} team owner role(s)");
            } elseif (isset($this->command)) {
                $this->command->warn('  ⚠ Could not assign permissions to team owners. Check that teams and roles tables exist.');
            }
        }

        if (isset($this->command)) {
            $this->command->info('✓ Voting permissions seeded successfully!');
            $this->command->line('');
            $this->command->comment('Available permissions:');
            foreach ($permissions as $permission) {
                $this->command->line("  • {$permission['name']} ({$permission['slug']})");
            }
        }
    }

    protected function assignPermissionsToTeamOwners(array $insertedPermissionIds, array $permissions, $now): int
    {
        if (! DB::getSchemaBuilder()->hasTable('teams') || ! DB::getSchemaBuilder()->hasTable('roles')) {
            return 0;
        }

        $userRoleTable = null;
        foreach (['user_role', 'role_user', 'user_roles', 'role_users'] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $userRoleTable = $table;
                break;
            }
        }

        if (! $userRoleTable) {
            return 0;
        }

        $rolePermissionColumns = DB::getSchemaBuilder()->getColumnListing('role_permission');
        $userRoleColumns = DB::getSchemaBuilder()->getColumnListing($userRoleTable);
        $rolesColumns = DB::getSchemaBuilder()->getColumnListing('roles');

        $hierarchyField = null;
        foreach (['hierarchy', 'hierarchy_number', 'level', 'order', 'hierarchy_level'] as $field) {
            if (in_array($field, $rolesColumns, true)) {
                $hierarchyField = $field;
                break;
            }
        }

        if (! $hierarchyField) {
            return 0;
        }

        $teams = DB::table('teams')
            ->whereNotNull('user_id')
            ->select('id', 'user_id')
            ->get();

        if ($teams->isEmpty()) {
            return 0;
        }

        $assignedCount = 0;
        $hasTimestamps = in_array('created_at', $rolePermissionColumns, true)
            && in_array('updated_at', $rolePermissionColumns, true);

        foreach ($teams as $team) {
            $ownerRolesQuery = DB::table($userRoleTable)
                ->join('roles', function ($join) use ($userRoleTable, $userRoleColumns) {
                    if (in_array('role_id', $userRoleColumns, true)) {
                        $join->on("{$userRoleTable}.role_id", '=', 'roles.id');
                    } elseif (in_array('role_slug', $userRoleColumns, true)) {
                        $join->on("{$userRoleTable}.role_slug", '=', 'roles.slug');
                    }
                })
                ->where("{$userRoleTable}.user_id", $team->user_id);

            if (in_array('team_id', $userRoleColumns, true)) {
                $ownerRolesQuery->where("{$userRoleTable}.team_id", $team->id);
            }

            $highestRole = $ownerRolesQuery
                ->select('roles.*')
                ->orderByDesc("roles.{$hierarchyField}")
                ->first();

            if (! $highestRole) {
                continue;
            }

            if (in_array('role_slug', $rolePermissionColumns, true) && in_array('permission_id', $rolePermissionColumns, true)) {
                foreach ($insertedPermissionIds as $permissionId) {
                    $data = [
                        'role_slug' => $highestRole->slug,
                        'permission_id' => $permissionId,
                    ];
                    if ($hasTimestamps) {
                        $data['created_at'] = $now;
                        $data['updated_at'] = $now;
                    }
                    DB::table('role_permission')->insertOrIgnore($data);
                }
                $assignedCount++;
            } elseif (in_array('role_slug', $rolePermissionColumns, true) && in_array('permission_slug', $rolePermissionColumns, true)) {
                foreach ($permissions as $permission) {
                    $data = [
                        'role_slug' => $highestRole->slug,
                        'permission_slug' => $permission['slug'],
                    ];
                    if ($hasTimestamps) {
                        $data['created_at'] = $now;
                        $data['updated_at'] = $now;
                    }
                    DB::table('role_permission')->insertOrIgnore($data);
                }
                $assignedCount++;
            } elseif (in_array('role_id', $rolePermissionColumns, true) && in_array('permission_id', $rolePermissionColumns, true)) {
                foreach ($insertedPermissionIds as $permissionId) {
                    $data = [
                        'role_id' => $highestRole->id,
                        'permission_id' => $permissionId,
                    ];
                    if ($hasTimestamps) {
                        $data['created_at'] = $now;
                        $data['updated_at'] = $now;
                    }
                    DB::table('role_permission')->insertOrIgnore($data);
                }
                $assignedCount++;
            }
        }

        return $assignedCount;
    }
}
