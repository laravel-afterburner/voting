<?php

namespace Afterburner\Voting\Support;

class GrantableVoterUnit
{
    public function __construct(
        public string $type,
        public int $id,
        public string $label,
    ) {}
}
