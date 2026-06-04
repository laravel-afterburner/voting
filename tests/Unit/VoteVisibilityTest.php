<?php

namespace Afterburner\Voting\Tests\Unit;

use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Tests\TestCase;

class VoteVisibilityTest extends TestCase
{
    public function test_secret_is_confidential(): void
    {
        $this->assertTrue(VoteVisibility::Secret->isConfidential());
        $this->assertFalse(VoteVisibility::VisibleAfterClose->isConfidential());
        $this->assertSame('Confidential', VoteVisibility::Secret->label());
    }
}
