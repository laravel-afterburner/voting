<?php

namespace Afterburner\Voting\Tests\Feature;

use Afterburner\Voting\Actions\CreateBallot;
use Afterburner\Voting\Actions\CreateProxy;
use Afterburner\Voting\Actions\PublishBallot;
use Afterburner\Voting\Enums\BallotStatus;
use Afterburner\Voting\Enums\BallotType;
use Afterburner\Voting\Enums\ElectorateType;
use Afterburner\Voting\Enums\VoteVisibility;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Providers\VotingServiceProvider;
use Afterburner\Voting\Support\TeamVotingSettings;
use Afterburner\Voting\Tests\Support\TestCustomElectorateResolver;
use Afterburner\Voting\Tests\TestCase;
use App\Models\User;

class Phase4TeamSettingsTest extends TestCase
{
    public function test_create_ballot_uses_team_voting_defaults(): void
    {
        [$user, $team] = $this->createTeamWithUser(['create_resolutions']);

        $settings = TeamVotingSettings::forTeam($team);
        $settings->update([
            'default_quorum_percent' => 66.67,
            'default_vote_visibility' => VoteVisibility::Secret,
        ]);

        $ballot = app(CreateBallot::class)->execute(
            $team,
            $user,
            'Team defaults ballot',
            null,
            BallotType::Resolution,
            ElectorateType::AllMembers,
        );

        $this->assertSame('66.67', (string) $ballot->quorum_percent);
        $this->assertSame(VoteVisibility::Secret, $ballot->vote_visibility);
    }

    public function test_publish_ballot_applies_team_quorum_when_missing(): void
    {
        [$user, $team] = $this->createTeamWithUser(['create_resolutions']);

        $settings = TeamVotingSettings::forTeam($team);
        $settings->update(['default_quorum_percent' => 55]);

        $ballot = $this->createOpenBallot($team, $user, [
            'status' => BallotStatus::Draft,
            'quorum_percent' => null,
            'published_at' => null,
        ]);

        $this->assertNull($ballot->quorum_percent);

        $published = app(PublishBallot::class)->execute($ballot, $user);

        $this->assertSame('55.00', (string) $published->quorum_percent);
    }

    public function test_create_proxy_respects_team_allow_proxy_setting(): void
    {
        [$user, $team] = $this->createTeamWithUser(['create_resolutions', 'manage_proxy_votes']);
        $proxyHolder = $this->createAdditionalUser($team, ['vote_resolutions'], 'proxy@example.com');

        $settings = TeamVotingSettings::forTeam($team);
        $settings->update(['allow_proxy_votes' => false]);

        $ballot = $this->createOpenBallot($team, $user);

        $this->expectException(VotingException::class);
        $this->expectExceptionMessage('Proxy votes are not enabled.');

        app(CreateProxy::class)->execute(
            $ballot,
            $user,
            $proxyHolder,
            User::class,
            $user->id,
        );
    }

    public function test_publish_custom_electorate_ballot_requires_resolver(): void
    {
        [$user, $team] = $this->createTeamWithUser(['create_resolutions']);

        config(['afterburner-voting.custom_electorate_resolver' => null]);

        $ballot = app(CreateBallot::class)->execute(
            $team,
            $user,
            'Custom electorate',
            null,
            BallotType::Resolution,
            ElectorateType::Custom,
            [],
            VoteVisibility::VisibleAfterClose,
            null,
            now()->subHour(),
            now()->addWeek(),
        );

        $this->expectException(VotingException::class);
        $this->expectExceptionMessage('Custom electorate ballots require');

        app(PublishBallot::class)->execute($ballot, $user);
    }

    public function test_publish_custom_electorate_ballot_succeeds_with_resolver(): void
    {
        [$user, $team] = $this->createTeamWithUser(['create_resolutions']);

        config([
            'afterburner-voting.custom_electorate_resolver' => TestCustomElectorateResolver::class,
        ]);

        $ballot = app(CreateBallot::class)->execute(
            $team,
            $user,
            'Custom electorate',
            null,
            BallotType::Resolution,
            ElectorateType::Custom,
            [],
            VoteVisibility::VisibleAfterClose,
            null,
            now()->subHour(),
            now()->addWeek(),
        );

        $published = app(PublishBallot::class)->execute($ballot, $user);

        $this->assertTrue($published->status->value === 'open');
    }

    public function test_invalid_custom_electorate_resolver_fails_on_boot(): void
    {
        config(['afterburner-voting.custom_electorate_resolver' => \stdClass::class]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        (new VotingServiceProvider($this->app))->register();
    }
}
