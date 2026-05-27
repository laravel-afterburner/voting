<?php

namespace Afterburner\Voting\Events;

use Afterburner\Voting\Models\BallotVoteRevocation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoteRevoked
{
    use Dispatchable, SerializesModels;

    public function __construct(public BallotVoteRevocation $revocation) {}
}
