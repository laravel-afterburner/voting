<?php

namespace Afterburner\Voting\Support;

class VotingPermissionDefinitions
{
    /**
     * @return array<int, array{name: string, slug: string, description: string}>
     */
    public static function all(): array
    {
        if (class_exists(\App\Support\PermissionCatalog::class)) {
            return collect(\App\Support\PermissionCatalog::definitions())
                ->filter(fn (array $permission) => in_array($permission['slug'], [
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
                ], true))
                ->values()
                ->all();
        }

        return [
            [
                'name' => 'Manage Ballots',
                'slug' => 'manage_ballots',
                'description' => 'Full admin: edit others drafts, cancel, force-close',
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
