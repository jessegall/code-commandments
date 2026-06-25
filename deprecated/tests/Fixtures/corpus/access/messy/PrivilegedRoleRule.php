<?php

namespace App\Access;

/**
 * Privileged roles get in.
 */
class PrivilegedRoleRule
{
    /**
     * @param array<string,mixed> $data
     */
    public function allows($data = [])
    {
        $actor = $data['actor'] ?? [];
        $role = $actor['role'] ?? 'guest';

        if ($role == 'admin' || $role == 'super') {
            return true;
        }

        $status = $actor['status'] ?? 'pending';
        if ($status === 'active' && $role === 'editor') {
            return true;
        }

        return false;
    }
}
