<?php

namespace Afterburner\Voting\Tests\Unit;

use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Services\QuorumService;
use Afterburner\Voting\Tests\Support\TestMultiUnitVoterEligibilityResolver;
use Afterburner\Voting\Tests\TestCase;

class QuorumServiceTest extends TestCase
{
    public function test_quorum_not_configured_when_percent_null(): void
    {
        config(['afterburner-voting.eligibility_resolver' => TestMultiUnitVoterEligibilityResolver::class]);
        $this->app->forgetInstance(VoterEligibilityResolver::class);

        [$user, $team] = $this->createTeamWithUser();
        $ballot = $this->createOpenBallot($team, $user, [
            'quorum_percent' => null,
        ]);

        $quorum = app(QuorumService::class)->calculate($ballot);

        $this->assertFalse($quorum['configured']);
        $this->assertNull($quorum['met']);
        $this->assertSame(40, $quorum['eligible']);
    }
}
