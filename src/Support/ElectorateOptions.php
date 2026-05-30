<?php

namespace Afterburner\Voting\Support;

use Afterburner\Voting\Enums\ElectorateType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ElectorateOptions
{
    /**
     * @param  array<int, string>|null  $selectedValues
     * @return array<int, array{value: string, label: string}>
     */
    public static function forSelect(?array $selectedValues = null): array
    {
        $options = [
            [
                'value' => ElectorateType::AllMembers->value,
                'label' => 'All members',
            ],
        ];

        foreach (self::roles() as $role) {
            $options[] = [
                'value' => $role->slug,
                'label' => $role->name,
            ];
        }

        if ($selectedValues !== null) {
            foreach ($selectedValues as $selectedValue) {
                if (! is_string($selectedValue) || $selectedValue === '') {
                    continue;
                }

                if (! self::containsValue($options, $selectedValue)) {
                    $options[] = [
                        'value' => $selectedValue,
                        'label' => Electorate::from($selectedValue)->label(),
                    ];
                }
            }
        }

        return $options;
    }

    /**
     * @param  array<int, string>|null  $selectedValues
     * @return array<int, string>
     */
    public static function allowedValues(?array $selectedValues = null): array
    {
        return array_column(self::forSelect($selectedValues), 'value');
    }

    /**
     * @return Collection<int, object{slug: string, name: string}>
     */
    protected static function roles(): Collection
    {
        if (! Schema::hasTable('roles')) {
            return collect();
        }

        $query = DB::table('roles')->select('slug', 'name');

        $hierarchyField = self::hierarchyField();

        if ($hierarchyField !== null) {
            $query->orderBy($hierarchyField);
        } else {
            $query->orderBy('name');
        }

        return $query->get();
    }

    protected static function hierarchyField(): ?string
    {
        if (! Schema::hasTable('roles')) {
            return null;
        }

        foreach (['hierarchy', 'hierarchy_number', 'level', 'order', 'hierarchy_level'] as $field) {
            if (Schema::hasColumn('roles', $field)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{value: string, label: string}>  $options
     */
    protected static function containsValue(array $options, string $value): bool
    {
        foreach ($options as $option) {
            if ($option['value'] === $value) {
                return true;
            }
        }

        return false;
    }
}
