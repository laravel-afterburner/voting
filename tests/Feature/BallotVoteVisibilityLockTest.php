<?php

namespace Afterburner\Voting\Tests\Feature;

use Afterburner\Voting\Actions\CastVote;
use Afterburner\Voting\Actions\PublishBallot;
use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Support\BallotVoteVisibilityGuard;
use Afterburner\Voting\Tests\TestCase;
use App\Models\User;

class BallotVoteVisibilityLockTest extends TestCase
{
    public function test_vote_visibility_locks_when_ballot_is_published(): void
    {
        [$user, $team] = $this->createTeamWithUser(['create_resolutions']);

        $ballot = $this->createOpenBallot($team, $user, [
            'status' => BallotStatus::Draft,
            'vote_visibility' => VoteVisibility::Secret,
            'published_at' => null,
            'opens_at' => now()->addDay(),
            'closes_at' => now()->addWeek(),
        ]);

        $this->assertFalse($ballot->voteVisibilityIsLocked());

        $published = app(PublishBallot::class)->execute($ballot, $user);

        $this->assertSame(BallotStatus::Scheduled, $published->status);
        $this->assertTrue($published->voteVisibilityIsLocked());

        $this->expectException(VotingException::class);

        BallotVoteVisibilityGuard::resolveForUpdate(
            $published,
            VoteVisibility::VisibleAfterClose,
        );
    }

    public function test_model_update_rejects_vote_visibility_change_after_publish(): void
    {
        [$user, $team] = $this->createTeamWithUser(['create_resolutions']);

        $ballot = $this->createOpenBallot($team, $user, [
            'vote_visibility' => VoteVisibility::Secret,
        ]);

        $this->assertTrue($ballot->voteVisibilityIsLocked());

        $this->expectException(VotingException::class);

        $ballot->update(['vote_visibility' => VoteVisibility::VisibleAfterClose]);
    }

    public function test_confidential_ballot_locks_visibility_after_first_vote_even_without_publish(): void
    {
        [$user, $team] = $this->createTeamWithUser(['vote_resolutions', 'create_resolutions']);

        $ballot = $this->createOpenBallot($team, $user, [
            'vote_visibility' => VoteVisibility::Secret,
            'published_at' => null,
        ]);

        $this->assertFalse($ballot->voteVisibilityIsLocked());

        app(CastVote::class)->execute(
            $ballot,
            $user,
            $ballot->options->firstWhere('label', 'Yes'),
            User::class,
            $user->id,
        );

        $ballot = $ballot->fresh();

        $this->assertTrue($ballot->voteVisibilityIsLocked());

        $this->expectException(VotingException::class);

        $ballot->update(['vote_visibility' => VoteVisibility::VisibleRealtime]);
    }

    public function test_draft_ballot_allows_visibility_changes_before_publish(): void
    {
        [$user, $team] = $this->createTeamWithUser(['create_resolutions']);

        $ballot = $this->createOpenBallot($team, $user, [
            'status' => BallotStatus::Draft,
            'vote_visibility' => VoteVisibility::Secret,
            'published_at' => null,
        ]);

        $this->assertFalse($ballot->voteVisibilityIsLocked());

        $updated = BallotVoteVisibilityGuard::resolveForUpdate(
            $ballot,
            VoteVisibility::VisibleAfterClose,
        );

        $this->assertSame(VoteVisibility::VisibleAfterClose, $updated);

        $ballot->update(['vote_visibility' => VoteVisibility::VisibleAfterClose]);

        $this->assertSame(VoteVisibility::VisibleAfterClose, $ballot->fresh()->vote_visibility);
    }
}
