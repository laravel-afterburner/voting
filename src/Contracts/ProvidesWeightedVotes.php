<?php

namespace Afterburner\Voting\Contracts;

use Afterburner\Voting\Models\Ballot;

interface ProvidesWeightedVotes
{
    /**
     * Voting weight for tally math (e.g. unit entitlement). Must be > 0.
     */
    public function voterUnitWeight(Ballot $ballot, string $voterUnitType, int $voterUnitId): float;
}
