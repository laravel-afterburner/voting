<?php

namespace Afterburner\Voting\Actions;

use Afterburner\Voting\Events\VoteRevoked;
use Afterburner\Voting\Exceptions\VotingException;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotResponse;
use Afterburner\Voting\Models\BallotVoteRevocation;
use Afterburner\Voting\Support\BallotParticipation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RevokeVote
{
    public function execute(
        Ballot $ballot,
        User $user,
        string $voterUnitType,
        int $voterUnitId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): BallotVoteRevocation {
        if (! config('afterburner-voting.allow_vote_revocation', false)) {
            throw new VotingException('Vote revocation is not enabled.');
        }

        if (! $ballot->isOpen()) {
            throw new VotingException('Votes can only be revoked while the ballot is open.');
        }

        if (BallotParticipation::unitHasRevocation($ballot, $voterUnitType, $voterUnitId)) {
            throw new VotingException('This vote has already been revoked.');
        }

        Gate::forUser($user)->authorize('revokeVote', [$ballot, $voterUnitType, $voterUnitId]);

        $response = BallotResponse::query()
            ->where('ballot_id', $ballot->id)
            ->where('voter_unit_type', $voterUnitType)
            ->where('voter_unit_id', $voterUnitId)
            ->first();

        if (! $response) {
            throw new VotingException('No vote exists for this voting unit.');
        }

        try {
            $revocation = DB::transaction(function () use ($ballot, $user, $voterUnitType, $voterUnitId, $response, $ipAddress, $userAgent) {
                $revocation = BallotVoteRevocation::query()->create([
                    'ballot_id' => $ballot->id,
                    'voter_unit_type' => $voterUnitType,
                    'voter_unit_id' => $voterUnitId,
                    'revoked_by_user_id' => $user->id,
                    'ballot_option_id' => $response->ballot_option_id,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'revoked_at' => now(),
                ]);

                $response->delete();

                return $revocation;
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                throw new VotingException('This vote has already been revoked.');
            }

            throw $exception;
        }

        VoteRevoked::dispatch($revocation);

        return $revocation->fresh(['option', 'revokedBy']);
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'ballot_vote_revocations_ballot_voter_unit_unique')
            || str_contains($message, 'UNIQUE constraint failed');
    }
}
