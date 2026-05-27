<?php

namespace Afterburner\Voting\Events;

use Afterburner\Voting\Models\Ballot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BallotOpened
{
    use Dispatchable, SerializesModels;

    public function __construct(public Ballot $ballot) {}
}
