<?php

namespace Afterburner\Voting\Support;

use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\Ballot;

class BallotVoteVisibilityGuard
{
    public static function resolveForUpdate(Ballot $ballot, VoteVisibility $requested): VoteVisibility
    {
        if (! $ballot->voteVisibilityIsLocked()) {
            return $requested;
        }

        if ($requested !== $ballot->vote_visibility) {
            throw new VotingException(
                'Vote visibility cannot be changed after this ballot has been published or has received confidential votes.',
            );
        }

        return $ballot->vote_visibility;
    }
}
