<?php

namespace Afterburner\Voting\Database\Seeders;

use Afterburner\Voting\Database\Seeders\Concerns\AssignsPermissionsToTeamOwners;
use Afterburner\Voting\Support\VotingPermissionDefinitions;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VotingPermissionsSeeder extends Seeder
{
    use AssignsPermissionsToTeamOwners;

    public function run(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permissions')) {
            if (isset($this->command)) {
                $this->command->error('Permissions table does not exist. Please ensure your database migrations are up to date.');
            }

            return;
        }

        $now = Carbon::now();
        $permissions = array_map(
            fn (array $permission) => $permission + ['created_at' => $now, 'updated_at' => $now],
            VotingPermissionDefinitions::all()
        );

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
}
