<?php

namespace App\FeatureEnvy\UserFullname;

/**
 * A user's job role within their organisation.
 */
enum JobRole: string
{
    /** Heads up the organisation. */
    case ChiefExecutive = 'chief_executive';
    /** Manages a team or department. */
    case Manager = 'manager';
    /** An individual contributor. */
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::ChiefExecutive => 'Chief Executive',
            self::Manager => 'Manager',
            self::Staff => 'Staff',
        };
    }
}
