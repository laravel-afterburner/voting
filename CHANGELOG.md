# Changelog

All notable changes to `laravel-afterburner/voting` are documented in this file.

## [Unreleased]

## [1.1.0] - 2026-05-29

### Added

- Proxy management UI at `/teams/{team}/voting/proxies` with optional `ProxyGrantResolver` contract
- `SubscriptionEntitlementGate` and `TeamPermissionGate` (aligned with documents package patterns)
- `VotingPermissionDefinitions` for centralised package permission slugs
- System Settings registration for the **Voting** section (`VotingSettings` Livewire component)
- `PackageSeederRegistry` registration for `VotingPermissionsSeeder`
- `AssignsPermissionsToTeamOwners` seeder concern
- `GrantableVoterUnit` support for proxy grant resolution
- LICENSE, `.editorconfig`, and local development setup documentation

### Changed

- Team voting settings moved from a standalone page to **System Settings → Voting** (`/teams/{team}/system-settings`); removed `/teams/{team}/voting-settings` route and team nav item
- Install command no longer publishes migrations (migrations load automatically from the package)
- Voting permissions seeder simplified; uses `VotingPermissionDefinitions` and assigns slugs to team owners
- Ballot and proxy policies updated for team owner access and subscription entitlement gating
- `team_voting_settings` table creation merged into the ballot vote revocations migration
- Navigation registers a **Proxy votes** child item when `AFTERBURNER_VOTING_PROXY_GRANT_RESOLVER` is configured

### Removed

- `VotingSettingsController` and standalone `voting-settings` view

## [1.0.0] - 2026-05-27

First stable release (Phases 1–4).

### Added

- Ballots, options, and responses with unique `(ballot_id, voter_unit_type, voter_unit_id)` constraint
- Default user eligibility resolver and pluggable `VoterEligibilityResolver` contract
- Draft/create/publish/close ballot flow with double-vote prevention
- Basic tally, results UI, and permissions seeder (`vote_resolutions`, `create_resolutions`, package slugs)
- Quorum tracking and UI widget; council vs all-members electorate
- Proxy votes (grant, revoke, cast via proxy); vote visibility modes
- CSV and PDF results export; weighted vote tally via `ProvidesWeightedVotes`
- Vote revocation (config-gated); scheduled auto-open/auto-close via queued jobs
- Voting audit events (`BallotPublished`, `BallotClosed`, `VoteCast`, etc.)
- `team_voting_settings` and Voting Settings page (`/teams/{team}/voting-settings`)
- Team defaults wired into `CreateBallot`, `PublishBallot`, and `CreateProxy`
- Boot-time validation for `AFTERBURNER_VOTING_CUSTOM_ELECTORATE_RESOLVER`
- `SendBallotPublishedVoterNotifications` listener stub for host-app hooks
- Optional document attachments via documents package (`ballot-documents` Livewire)
- `afterburner:voting:install` command and team navigation registration

### Changed

- New ballot form pre-fills quorum and visibility from team settings
