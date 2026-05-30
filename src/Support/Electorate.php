<?php

namespace Afterburner\Voting\Support;

use Afterburner\Voting\Enums\ElectorateType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stringable;

final class Electorate implements Stringable
{
    public function __construct(
        public readonly string $value,
    ) {}

    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value instanceof ElectorateType) {
            return new self($value->value);
        }

        if (is_array($value)) {
            return self::fromSelection($value);
        }

        $stringValue = (string) $value;

        if (str_starts_with($stringValue, '[')) {
            $decoded = json_decode($stringValue, true);

            if (is_array($decoded) && $decoded !== []) {
                return new self(json_encode(array_values(array_unique($decoded)), JSON_THROW_ON_ERROR));
            }
        }

        return new self($stringValue);
    }

    /**
     * @param  array<int, string>  $values
     */
    public static function fromSelection(array $values): self
    {
        $values = array_values(array_unique(array_filter($values, fn ($value) => is_string($value) && $value !== '')));

        if ($values === []) {
            throw new \InvalidArgumentException('Electorate selection cannot be empty.');
        }

        if (in_array(ElectorateType::AllMembers->value, $values, true)) {
            return new self(ElectorateType::AllMembers->value);
        }

        if (count($values) === 1) {
            return new self($values[0]);
        }

        $roles = array_values(array_filter(
            $values,
            fn (string $value) => ! in_array($value, [
                ElectorateType::Council->value,
                ElectorateType::Custom->value,
            ], true)
        ));

        if ($roles === []) {
            return new self($values[0]);
        }

        sort($roles);

        return new self(json_encode($roles, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<int, string>
     */
    public function toSelection(): array
    {
        if ($this->isAllMembers()) {
            return [ElectorateType::AllMembers->value];
        }

        if ($this->isCouncil()) {
            return [ElectorateType::Council->value];
        }

        if ($this->isCustom()) {
            return [ElectorateType::Custom->value];
        }

        return $this->roleSlugs();
    }

    public function isAllMembers(): bool
    {
        return $this->value === ElectorateType::AllMembers->value;
    }

    public function isCouncil(): bool
    {
        return $this->value === ElectorateType::Council->value;
    }

    public function isCustom(): bool
    {
        return $this->value === ElectorateType::Custom->value;
    }

    public function isMultiRole(): bool
    {
        return str_starts_with($this->value, '[');
    }

    public function isRole(): bool
    {
        return ! in_array($this->value, [
            ElectorateType::AllMembers->value,
            ElectorateType::Council->value,
            ElectorateType::Custom->value,
        ], true) && ! $this->isMultiRole();
    }

    /**
     * @return array<int, string>
     */
    public function roleSlugs(): array
    {
        if ($this->isAllMembers() || $this->isCustom()) {
            return [];
        }

        if ($this->isCouncil()) {
            return config('afterburner-voting.council_role_slugs', []);
        }

        if ($this->isMultiRole()) {
            $decoded = json_decode($this->value, true);

            return is_array($decoded) ? array_values($decoded) : [];
        }

        if ($this->isRole()) {
            return [$this->value];
        }

        return [];
    }

    public function label(): string
    {
        if ($this->isAllMembers()) {
            return 'All members';
        }

        if ($this->isCouncil()) {
            return 'Council';
        }

        if ($this->isCustom()) {
            return 'Custom';
        }

        $labels = collect($this->roleSlugs())
            ->map(fn (string $slug) => self::roleLabel($slug))
            ->all();

        return $labels !== [] ? implode(', ', $labels) : str($this->value)->headline()->toString();
    }

    public function __toString(): string
    {
        return $this->value;
    }

    protected static function roleLabel(string $slug): string
    {
        if (! Schema::hasTable('roles')) {
            return str($slug)->headline()->toString();
        }

        $name = DB::table('roles')->where('slug', $slug)->value('name');

        return is_string($name) && $name !== '' ? $name : str($slug)->headline()->toString();
    }
}
