<?php

namespace Afterburner\Voting\Events;

use Afterburner\Voting\Models\Ballot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BallotReopened
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ballot $ballot,
        public ?int $reopenedByUserId = null,
    ) {}
}
