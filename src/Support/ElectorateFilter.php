<?php

namespace Afterburner\Voting\Support;

use Afterburner\Voting\Contracts\CustomElectorateResolver;
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
        $electorate = $ballot->electorate;

        if ($electorate->isAllMembers()) {
            return true;
        }

        if ($electorate->isCouncil()) {
            return $this->userHasCouncilRole($user, $ballot->team_id);
        }

        if ($electorate->isCustom()) {
            return $this->customResolver()?->userIsEligible($user, $ballot) ?? false;
        }

        $roleSlugs = $electorate->roleSlugs();

        if ($roleSlugs === []) {
            return false;
        }

        return $this->userHasAnyRole($user, $ballot->team_id, $roleSlugs);
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
        if ($ballot->electorate->isCustom()) {
            return $this->customResolver()?->totalEligibleVoterUnits($ballot) ?? 0;
        }

        return $ballot->team
            ->users()
            ->get()
            ->filter(fn (User $user) => TeamPermissionGate::allows($user, $ballot->team_id, 'vote_resolutions'))
            ->pipe(fn (Collection $users) => $this->filterUsers($users, $ballot))
            ->count();
    }

    protected function userHasCouncilRole(User $user, int $teamId): bool
    {
        $slugs = CouncilRoleSlugs::resolve();

        if ($slugs === []) {
            return false;
        }

        return $this->userHasAnyRole($user, $teamId, $slugs);
    }

    /**
     * @param  array<int, string>  $roleSlugs
     */
    protected function userHasAnyRole(User $user, int $teamId, array $roleSlugs): bool
    {
        if ($roleSlugs === []) {
            return false;
        }

        return DB::table('user_role')
            ->join('roles', 'user_role.role_id', '=', 'roles.id')
            ->where('user_role.user_id', $user->id)
            ->where('user_role.team_id', $teamId)
            ->whereIn('roles.slug', $roleSlugs)
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
