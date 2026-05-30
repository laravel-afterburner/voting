<?php

namespace Afterburner\Voting\Support;

use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Models\BallotVoteRevocation;
use App\Models\User;

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

    public static function userHasPendingVote(User $user, Ballot $ballot, VoterEligibilityResolver $resolver): bool
    {
        return $resolver->eligibleVoterUnits($user, $ballot)->contains(
            fn (VoterUnit $unit) => $resolver->canCastVote($user, $ballot, $unit->type, $unit->id),
        );
    }
}
