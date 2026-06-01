<?php

namespace Afterburner\Voting\Livewire\Ballots;

use Afterburner\Voting\Actions\CloseBallot;
use Afterburner\Voting\Actions\DeleteBallot;
use Afterburner\Voting\Actions\PublishBallot;
use Afterburner\Voting\Actions\RevokeVote;
use Afterburner\Voting\Concerns\FlashesNativeBanner;
use Afterburner\Voting\Contracts\VoterEligibilityResolver;
use Afterburner\Voting\Models\Ballot;
use Afterburner\Voting\Models\BallotResponse;
use App\Models\Team;
use App\Models\User;
use App\Traits\InteractsWithBanner;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    use FlashesNativeBanner;
    use InteractsWithBanner;

    public int $teamId;

    public int $ballotId;

    public bool $confirmingBallotDeletion = false;

    public function mount(Team $team, Ballot $ballot): void
    {
        if ($ballot->team_id !== $team->id) {
            abort(404);
        }

        if (! Auth::user()->belongsToTeam($team)) {
            abort(403, 'Access denied.');
        }

        abort_unless(Auth::user()->can('view', $ballot), 403);

        $this->teamId = $team->id;
        $this->ballotId = $ballot->id;
    }

    public function publishBallot(): void
    {
        $ballot = $this->ballot();

        try {
            app(PublishBallot::class)->execute($ballot, Auth::user());
            $this->banner(__('Ballot published successfully.'));
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function revokeVote(int $responseId): void
    {
        $ballot = $this->ballot();

        $response = BallotResponse::query()
            ->where('ballot_id', $ballot->id)
            ->where('id', $responseId)
            ->firstOrFail();

        try {
            app(RevokeVote::class)->execute(
                $ballot,
                Auth::user(),
                $response->voter_unit_type,
                $response->voter_unit_id,
                request()->ip(),
                request()->userAgent(),
            );
            $this->banner(__('Your vote has been revoked.'));
            $this->dispatch('refresh-notifications');
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function closeBallot(): void
    {
        $ballot = $this->ballot();

        try {
            app(CloseBallot::class)->execute($ballot, Auth::user());
            $this->banner(__('Ballot closed successfully.'));
        } catch (\Throwable $exception) {
            $this->dangerBanner($exception->getMessage());
        }
    }

    public function confirmBallotDeletion(): void
    {
        abort_unless(Auth::user()->can('delete', $this->ballot()), 403);

        $this->confirmingBallotDeletion = true;
    }

    public function cancelBallotDeletion(): void
    {
        $this->confirmingBallotDeletion = false;
    }

    public function deleteBallot()
    {
        $ballot = $this->ballot();

        try {
            app(DeleteBallot::class)->execute($ballot, Auth::user());
            $this->flashSuccessBanner(__('Ballot deleted successfully.'));

            return $this->redirectRoute('teams.ballots.index', ['team' => $this->teamId]);
        } catch (\Throwable $exception) {
            $this->confirmingBallotDeletion = false;
            $this->dangerBanner($exception->getMessage());
        }
    }

    #[On('vote-cast')]
    public function refreshAfterVote(): void
    {
        // Trigger a re-render after a nested vote form submits.
    }

    protected function ballot(): Ballot
    {
        return Ballot::query()
            ->with(['options', 'creator', 'team'])
            ->where('team_id', $this->teamId)
            ->findOrFail($this->ballotId);
    }

    public function render()
    {
        $ballot = $this->ballot();
        $team = Team::query()->findOrFail($this->teamId);
        $user = Auth::user();
        $resolver = app(VoterEligibilityResolver::class);

        $eligibleUnits = $resolver->eligibleVoterUnits($user, $ballot);
        $responses = BallotResponse::query()
            ->where('ballot_id', $ballot->id)
            ->where(function ($query) use ($user) {
                $query->where('cast_by_user_id', $user->id)
                    ->orWhere(function ($inner) use ($user) {
                        $inner->where('voter_unit_type', User::class)
                            ->where('voter_unit_id', $user->id);
                    });
            })
            ->with('option')
            ->get();

        return view('afterburner-voting::ballots.livewire.show', [
            'team' => $team,
            'ballot' => $ballot,
            'eligibleUnits' => $eligibleUnits,
            'responses' => $responses,
            'canVote' => $user->can('vote', $ballot),
            'canPublish' => $user->can('publish', $ballot),
            'canClose' => $user->can('close', $ballot),
            'canViewResults' => $user->can('viewResults', $ballot),
            'canUpdate' => $user->can('update', $ballot),
            'canDelete' => $user->can('delete', $ballot),
            'allowVoteRevocation' => (bool) config('afterburner-voting.allow_vote_revocation', false),
        ]);
    }
}
