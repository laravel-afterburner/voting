<?php

namespace Afterburner\Voting\Events;

use Afterburner\Voting\Models\BallotResponse;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoteCast
{
    use Dispatchable, SerializesModels;

    public function __construct(public BallotResponse $response) {}
}
