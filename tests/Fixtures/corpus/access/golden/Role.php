<?php

declare(strict_types=1);

namespace App\Access;

/**
 * The fixed set of roles an actor in the system can hold.
 */
enum Role: string
{
    /** Unrestricted access to every resource and administrative action. */
    case Admin = 'admin';

    /** Day-to-day staff member; may manage content but not the platform. */
    case Editor = 'editor';

    /** Read-only participant who can view but never mutate. */
    case Viewer = 'viewer';

    /** Unauthenticated or anonymous actor with the narrowest surface. */
    case Guest = 'guest';

    /**
     * The permissions this role is granted by default, as a fresh set.
     */
    public function defaultPermissions(): PermissionSet
    {
        return match ($this) {
            Role::Admin => PermissionSet::of(
                Permission::ViewContent,
                Permission::EditContent,
                Permission::DeleteContent,
                Permission::ManageUsers,
            ),
            Role::Editor => PermissionSet::of(
                Permission::ViewContent,
                Permission::EditContent,
            ),
            Role::Viewer => PermissionSet::of(
                Permission::ViewContent,
            ),
            Role::Guest => PermissionSet::empty(),
        };
    }

    /**
     * Whether this role outranks the platform-management boundary.
     */
    public function isPrivileged(): bool
    {
        return match ($this) {
            Role::Admin => true,
            Role::Editor, Role::Viewer, Role::Guest => false,
        };
    }
}
