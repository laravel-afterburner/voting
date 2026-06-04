<?php

namespace Afterburner\Voting\Support;

class VotingPermissionDefinitions
{
    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return [
            'view_voting',
            'view_ballots',
            'view_proxy_votes',
            'vote_resolutions',
            'create_resolutions',
            'manage_ballots',
            'edit_ballots',
            'close_ballots',
            'cancel_ballots',
            'delete_ballots',
            'view_ballot_results',
            'manage_proxy_votes',
            'export_ballot_results',
        ];
    }

    /**
     * @return array<int, array{name: string, slug: string, description: string}>
     */
    public static function all(): array
    {
        if (class_exists(\App\Support\PermissionCatalog::class)) {
            return collect(\App\Support\PermissionCatalog::definitions())
                ->filter(fn (array $permission) => in_array($permission['slug'], self::slugs(), true))
                ->values()
                ->all();
        }

        return [
            [
                'name' => 'Vote Resolutions',
                'slug' => 'vote_resolutions',
                'description' => 'Vote on team resolutions',
            ],
            [
                'name' => 'Create Resolutions',
                'slug' => 'create_resolutions',
                'description' => 'Create proposals for voting',
            ],
            [
                'name' => 'Manage Ballots',
                'slug' => 'manage_ballots',
                'description' => 'Full admin: edit others drafts, cancel, force-close',
            ],
            [
                'name' => 'Edit Ballots',
                'slug' => 'edit_ballots',
                'description' => 'Edit draft ballots created by others',
            ],
            [
                'name' => 'Close Ballots',
                'slug' => 'close_ballots',
                'description' => 'Close ballots for voting',
            ],
            [
                'name' => 'Cancel Ballots',
                'slug' => 'cancel_ballots',
                'description' => 'Cancel open ballots',
            ],
            [
                'name' => 'Delete Ballots',
                'slug' => 'delete_ballots',
                'description' => 'Delete draft ballots',
            ],
            [
                'name' => 'View Ballot Results',
                'slug' => 'view_ballot_results',
                'description' => 'View ballot results based on visibility rules',
            ],
            [
                'name' => 'Manage Proxy Votes',
                'slug' => 'manage_proxy_votes',
                'description' => 'Grant and revoke proxy votes',
            ],
            [
                'name' => 'Export Ballot Results',
                'slug' => 'export_ballot_results',
                'description' => 'Download CSV or PDF of ballot results',
            ],
        ];
    }
}
