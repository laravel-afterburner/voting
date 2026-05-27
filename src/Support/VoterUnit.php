<?php

namespace Afterburner\Voting\Support;

class VoterUnit
{
    public function __construct(
        public readonly string $type,
        public readonly int $id,
    ) {}

    public function key(): string
    {
        return $this->type.'|'.$this->id;
    }

    public function matches(string $type, int $id): bool
    {
        return $this->type === $type && $this->id === $id;
    }
}
