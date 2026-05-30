<?php

namespace Afterburner\Voting\Casts;

use Afterburner\Voting\Support\Electorate;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ElectorateCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Electorate
    {
        if ($value === null) {
            return null;
        }

        return Electorate::from($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return Electorate::from($value)->value;
    }
}
