<?php

namespace Afterburner\Voting\Support;

use Afterburner\Voting\Contracts\CustomElectorateResolver;
use Afterburner\Voting\Enums\ElectorateType;
use Afterburner\Voting\Models\Ballot;
use App\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ElectorateFilter
{
    public function __construct(
        protected Application $app,
    ) {}

    public function userMeetsElectorate(User $user, Ballot $ballot): bool
    {
        return match ($ballot->electorate) {
            ElectorateType::AllMembers => true,
            ElectorateType::Council => $this->userHasCouncilRole($user, $ballot->team_id),
            ElectorateType::Custom => $this->customResolver()?->userIsEligible($user, $ballot) ?? false,
        };
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    public function filterUsers(Collection $users, Ballot $ballot): Collection
    {
        return $users->filter(fn (User $user) => $this->userMeetsElectorate($user, $ballot))->values();
    }

    public function totalEligibleUsers(Ballot $ballot): int
    {
        if ($ballot->electorate === ElectorateType::Custom) {
            return $this->customResolver()?->totalEligibleVoterUnits($ballot) ?? 0;
        }

        return $ballot->team
            ->users()
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('vote_resolutions', $ballot->team_id))
            ->pipe(fn (Collection $users) => $this->filterUsers($users, $ballot))
            ->count();
    }

    protected function userHasCouncilRole(User $user, int $teamId): bool
    {
        $slugs = config('afterburner-voting.council_role_slugs', []);

        if ($slugs === []) {
            return false;
        }

        return DB::table('user_role')
            ->join('roles', 'user_role.role_id', '=', 'roles.id')
            ->where('user_role.user_id', $user->id)
            ->where('user_role.team_id', $teamId)
            ->whereIn('roles.slug', $slugs)
            ->exists();
    }

    protected function customResolver(): ?CustomElectorateResolver
    {
        $class = config('afterburner-voting.custom_electorate_resolver');

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            return null;
        }

        $resolver = $this->app->make($class);

        return $resolver instanceof CustomElectorateResolver ? $resolver : null;
    }
}
