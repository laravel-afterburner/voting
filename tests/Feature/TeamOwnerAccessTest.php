<?php

namespace Afterburner\Voting\Tests\Feature;

use App\Support\TeamPermissionGate;
use Afterburner\Voting\Tests\TestCase;
use App\Models\Team;
use App\Models\User;

class TeamOwnerAccessTest extends TestCase
{
    public function test_team_owner_is_granted_permissions_without_role_assignment(): void
    {
        $this->seedPermissions();

        $owner = User::query()->create([
            'name' => 'Team Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $team = Team::query()->create([
            'name' => 'Owner Team',
            'user_id' => $owner->id,
        ]);

        $this->assertTrue(TeamPermissionGate::ownsTeam($owner, $team->id));
        $this->assertTrue(TeamPermissionGate::allows($owner, $team->id, 'manage_ballots'));
        $this->assertTrue(TeamPermissionGate::allows($owner, $team->id, 'vote_resolutions'));
    }

    public function test_non_owner_still_requires_permission(): void
    {
        $this->seedPermissions();

        $owner = User::query()->create([
            'name' => 'Team Owner',
            'email' => 'owner2@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $team = Team::query()->create([
            'name' => 'Member Team',
            'user_id' => $owner->id,
        ]);

        $member = User::query()->create([
            'name' => 'Member',
            'email' => 'member@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $team->users()->attach($member);

        $this->assertFalse(TeamPermissionGate::allows($member, $team->id, 'manage_ballots'));
    }
}
