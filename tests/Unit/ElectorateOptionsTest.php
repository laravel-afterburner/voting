<?php

namespace Afterburner\Voting\Tests\Unit;

use Afterburner\Voting\Support\Electorate;
use Afterburner\Voting\Support\ElectorateOptions;
use Afterburner\Voting\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ElectorateOptionsTest extends TestCase
{
    public function test_for_select_includes_all_members_and_roles(): void
    {
        DB::table('roles')->insert([
            ['name' => 'President', 'slug' => 'president', 'hierarchy' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Owner', 'slug' => 'strata_owner', 'hierarchy' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $options = ElectorateOptions::forSelect();

        $this->assertSame('all_members', $options[0]['value']);
        $this->assertSame('All members', $options[0]['label']);
        $this->assertSame('president', $options[1]['value']);
        $this->assertSame('President', $options[1]['label']);
        $this->assertSame('strata_owner', $options[2]['value']);
        $this->assertSame('Owner', $options[2]['label']);
    }

    public function test_for_select_includes_legacy_selected_value(): void
    {
        $options = ElectorateOptions::forSelect(['council']);

        $this->assertTrue(collect($options)->contains(fn (array $option) => $option['value'] === 'council'));
        $this->assertSame('Council', collect($options)->firstWhere('value', 'council')['label']);
    }

    public function test_electorate_labels_role_slug_from_database(): void
    {
        DB::table('roles')->insert([
            'name' => 'Treasurer',
            'slug' => 'treasurer',
            'hierarchy' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('Treasurer', Electorate::from('treasurer')->label());
    }

    public function test_from_selection_stores_multiple_roles_as_json(): void
    {
        $electorate = Electorate::fromSelection(['president', 'treasurer']);

        $this->assertSame('["president","treasurer"]', $electorate->value);
        $this->assertSame(['president', 'treasurer'], $electorate->toSelection());
        $this->assertSame(['president', 'treasurer'], $electorate->roleSlugs());
    }

    public function test_from_selection_all_members_is_exclusive(): void
    {
        $electorate = Electorate::fromSelection(['all_members', 'president']);

        $this->assertSame('all_members', $electorate->value);
    }

    public function test_electorate_labels_multiple_roles(): void
    {
        DB::table('roles')->insert([
            ['name' => 'President', 'slug' => 'president', 'hierarchy' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Treasurer', 'slug' => 'treasurer', 'hierarchy' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $electorate = Electorate::from('["president","treasurer"]');

        $this->assertSame('President, Treasurer', $electorate->label());
    }
}
