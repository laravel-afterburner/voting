<?php

namespace Afterburner\Voting\Support;

use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Models\BallotVoteRevocation;

class BallotParticipation
{
    public static function unitHasResponse(Ballot $ballot, string $voterUnitType, int $voterUnitId): bool
    {
        return BallotResponse::query()
            ->where('ballot_id', $ballot->id)
            ->where('voter_unit_type', $voterUnitType)
            ->where('voter_unit_id', $voterUnitId)
            ->exists();
    }

    public static function unitHasRevocation(Ballot $ballot, string $voterUnitType, int $voterUnitId): bool
    {
        return BallotVoteRevocation::query()
            ->where('ballot_id', $ballot->id)
            ->where('voter_unit_type', $voterUnitType)
            ->where('voter_unit_id', $voterUnitId)
            ->exists();
    }

    public static function unitParticipated(Ballot $ballot, string $voterUnitType, int $voterUnitId): bool
    {
        return self::unitHasResponse($ballot, $voterUnitType, $voterUnitId)
            || self::unitHasRevocation($ballot, $voterUnitType, $voterUnitId);
    }
}
