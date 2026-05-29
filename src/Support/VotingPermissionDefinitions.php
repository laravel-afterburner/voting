<?php

namespace Afterburner\Voting\Support;

class VotingPermissionDefinitions
{
    /**
     * @return array<int, array{name: string, slug: string, description: string}>
     */
    public static function all(): array
    {
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
