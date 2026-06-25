<?php

namespace App\Access;

/**
 * A single, atomic capability that a role or actor may be granted.
 */
enum Permission: string
{
    /** Read content the actor has visibility of. */
    case ViewContent = 'content.view';

    /** Create or modify content. */
    case EditContent = 'content.edit';

    /** Permanently remove content. */
    case DeleteContent = 'content.delete';

    /** Administer user accounts and their roles. */
    case ManageUsers = 'users.manage';

    /**
     * Whether exercising this permission mutates state (vs. a pure read).
     */
    public function isMutating(): bool
    {
        return match ($this) {
            Permission::ViewContent => false,
            Permission::EditContent,
            Permission::DeleteContent,
            Permission::ManageUsers => true,
        };
    }
}
