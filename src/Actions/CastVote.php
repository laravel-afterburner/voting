<?php

namespace Afterburner\Voting\Actions;

use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Events\VoteCast;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotOption;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Models\ProxyVote;
use Afterburner\Voting\Support\BallotParticipation;
use Afterburner\Voting\Support\TeamVotingSettings;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CastVote
{
    public function __construct(
        protected VoterEligibilityResolver $resolver,
    ) {}

    public function execute(
        Ballot $ballot,
        User $user,
        BallotOption $option,
        string $voterUnitType,
        int $voterUnitId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?int $proxyVoteId = null,
    ): BallotResponse {
        if (! $ballot->isOpen()) {
            throw new VotingException('This ballot is not open for voting.');
        }

        if ($option->ballot_id !== $ballot->id) {
            throw new VotingException('Invalid ballot option.');
        }

        if (BallotParticipation::unitHasRevocation($ballot, $voterUnitType, $voterUnitId)) {
            throw new VotingException('This voting unit has revoked its vote and cannot vote again on this ballot.');
        }

        $proxy = $this->resolveProxy($ballot, $user, $voterUnitType, $voterUnitId, $proxyVoteId);

        try {
            $response = DB::transaction(function () use ($ballot, $user, $option, $voterUnitType, $voterUnitId, $ipAddress, $userAgent, $proxy) {
                $existing = BallotResponse::query()
                    ->where('ballot_id', $ballot->id)
                    ->where('voter_unit_type', $voterUnitType)
                    ->where('voter_unit_id', $voterUnitId)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ($existing->cast_by_user_id !== $user->id) {
                        throw new VotingException('This voting unit has already cast a vote.');
                    }

                    Gate::forUser($user)->authorize('vote', $ballot);

                    if (! $this->resolver->canChangeVote($user, $ballot, $voterUnitType, $voterUnitId)) {
                        throw new VotingException('This vote can no longer be changed.');
                    }

                    $existing->update([
                        'ballot_option_id' => $option->id,
                        'cast_by_user_id' => $user->id,
                        'proxy_vote_id' => $proxy?->id,
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                        'cast_at' => now(),
                    ]);

                    return $existing;
                }

                Gate::forUser($user)->authorize('vote', $ballot);

                if (! $this->resolver->canCastVote($user, $ballot, $voterUnitType, $voterUnitId)) {
                    throw new VotingException('You are not eligible to cast a vote for this unit.');
                }

                return BallotResponse::query()->create([
                    'ballot_id' => $ballot->id,
                    'ballot_option_id' => $option->id,
                    'cast_by_user_id' => $user->id,
                    'voter_unit_type' => $voterUnitType,
                    'voter_unit_id' => $voterUnitId,
                    'proxy_vote_id' => $proxy?->id,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'cast_at' => now(),
                ]);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                throw new VotingException('This voting unit has already cast a vote.');
            }

            throw $exception;
        }

        VoteCast::dispatch($response);

        return $response->fresh(['option', 'castBy']);
    }

    protected function resolveProxy(
        Ballot $ballot,
        User $user,
        string $voterUnitType,
        int $voterUnitId,
        ?int $proxyVoteId,
    ): ?ProxyVote {
        if ($proxyVoteId === null) {
            if ($voterUnitType === User::class && $voterUnitId === $user->id) {
                return null;
            }

            if (! TeamVotingSettings::allowProxyVotesForTeam($ballot->team)) {
                return null;
            }

            $proxy = ProxyVote::query()
                ->where('ballot_id', $ballot->id)
                ->where('proxy_holder_user_id', $user->id)
                ->where('grantor_voter_unit_type', $voterUnitType)
                ->where('grantor_voter_unit_id', $voterUnitId)
                ->active()
                ->first();

            if ($proxy) {
                Gate::forUser($user)->authorize('exercise', $proxy);
            }

            return $proxy;
        }

        if (! TeamVotingSettings::allowProxyVotesForTeam($ballot->team)) {
            throw new VotingException('Proxy votes are not enabled.');
        }

        $proxy = ProxyVote::query()
            ->where('id', $proxyVoteId)
            ->where('ballot_id', $ballot->id)
            ->first();

        if (! $proxy || ! $proxy->isActive()) {
            throw new VotingException('Invalid or expired proxy.');
        }

        if ($proxy->proxy_holder_user_id !== $user->id) {
            throw new VotingException('You are not the proxy holder for this vote.');
        }

        if (! $proxy->matchesGrantor($voterUnitType, $voterUnitId)) {
            throw new VotingException('Proxy does not match this voting unit.');
        }

        Gate::forUser($user)->authorize('exercise', $proxy);

        return $proxy;
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'ballot_responses_ballot_voter_unit_unique')
            || str_contains($message, 'UNIQUE constraint failed');
    }
}
