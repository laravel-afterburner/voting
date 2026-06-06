<?php

namespace Afterburner\Voting\Support;

use App\Models\Team;
use App\Models\User;
use App\Support\TeamPermissionGate;

/**
 * Voting module areas (ballots, proxies, results) mapped to permission slugs.
 */
final class VotingPermissions
{
    public const SECTION_BALLOTS = 'ballots';

    public const SECTION_PROXIES = 'proxies';

    public const SECTION_RESULTS = 'results';

    /**
     * @return array<string, string>
     */
    public static function sectionPermissionMap(): array
    {
        return [
            self::SECTION_BALLOTS => 'view_ballots',
            self::SECTION_PROXIES => 'view_proxy_votes',
            self::SECTION_RESULTS => 'view_ballot_results',
        ];
    }

    /**
     * @return list<string>
     */
    public static function sectionDisplayOrder(): array
    {
        $sections = [
            self::SECTION_BALLOTS,
            self::SECTION_RESULTS,
        ];

        if (! empty(config('afterburner-voting.proxy_grant_resolver'))) {
            array_splice($sections, 1, 0, [self::SECTION_PROXIES]);
        }

        return $sections;
    }

    /**
     * @return list<string>
     */
    public static function moduleAccessSlugs(): array
    {
        return [
            'view_voting',
            'view_ballots',
            'view_proxy_votes',
            'view_ballot_results',
            'vote_resolutions',
            'create_resolutions',
            'manage_ballots',
            'edit_ballots',
            'close_ballots',
            'cancel_ballots',
            'delete_ballots',
            'manage_proxy_votes',
            'export_ballot_results',
        ];
    }

    public static function canAccessModule(User $user, Team $team): bool
    {
        return TeamPermissionGate::allowsAny($user, $team->id, self::moduleAccessSlugs());
    }

    public static function canViewSection(User $user, Team $team, string $section): bool
    {
        $slug = self::sectionPermissionMap()[$section] ?? null;

        if ($slug === null) {
            return false;
        }

        if ($section === self::SECTION_BALLOTS) {
            return TeamPermissionGate::allowsAny($user, $team->id, [
                $slug,
                'vote_resolutions',
                'create_resolutions',
                'manage_ballots',
            ]);
        }

        if ($section === self::SECTION_PROXIES) {
            return TeamPermissionGate::allowsAny($user, $team->id, [
                $slug,
                'manage_proxy_votes',
            ]);
        }

        return TeamPermissionGate::allows($user, $team->id, $slug);
    }

    /**
     * @return list<string>
     */
    public static function visibleSections(User $user, Team $team): array
    {
        $visible = [];

        foreach (self::sectionDisplayOrder() as $section) {
            if (self::canViewSection($user, $team, $section)) {
                $visible[] = $section;
            }
        }

        return $visible;
    }
}
