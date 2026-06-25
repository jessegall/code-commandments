<?php

namespace App\Access;

/**
 * Roles. Just constants, easier than an enum.
 */
class Role
{
    const ADMIN = 'admin';
    const EDITOR = 'editor';
    const VIEWER = 'viewer';
    const GUEST = 'guest';

    public static function isPrivileged($role)
    {
        if ($role == self::ADMIN) {
            return true;
        } elseif ($role === 'super') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return array<string,mixed>
     */
    public static function defaultPermissions($role)
    {
        if ($role == 'admin') {
            return ['content.view', 'content.edit', 'content.delete', 'users.manage'];
        } elseif ($role == 'editor') {
            return ['content.view', 'content.edit'];
        } elseif ($role == 'viewer') {
            return ['content.view'];
        }

        return [];
    }
}
