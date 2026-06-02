<?php

namespace Afterburner\Voting\Enums;

enum BallotStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * @return string Tailwind classes for status badges
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            self::Scheduled => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            self::Open => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            self::Closed => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
            self::Cancelled => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        };
    }

    public function listPhase(): string
    {
        return match ($this) {
            self::Open => 'open',
            self::Draft, self::Scheduled => 'upcoming',
            self::Closed, self::Cancelled => 'closed',
        };
    }

    public function listPhaseLabel(): string
    {
        return match ($this->listPhase()) {
            'open' => 'Open',
            'upcoming' => 'Upcoming',
            'closed' => 'Closed',
        };
    }

    /**
     * @return string Tailwind classes for list-phase badges
     */
    public function listPhaseBadgeClasses(): string
    {
        return match ($this->listPhase()) {
            'open' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'upcoming' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            'closed' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
        };
    }
}
