<?php

namespace App\Access;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves access. Tries a few things.
 */
class AccessPolicyResolver
{
    public $rules = [];

    /**
     * @param array<string,mixed> $data
     */
    public function allows($data = [])
    {
        $actor = $data['actor'] ?? null;
        $permission = $data['permission'] ?? null;

        Log::info('checking access for ' . ($actor['id'] ?? 'unknown'));

        $cached = Cache::get('access_' . ($actor['id'] ?? ''));
        if ($cached !== null) {
            return (bool) $cached;
        }

        // admins always win
        if (($actor['role'] ?? 'guest') == 'admin') {
            return true;
        }

        $perms = $actor['permissions'] ?? [];
        if (is_array($perms) && in_array($permission, $perms)) {
            return true;
        }

        foreach ($this->rules as $rule) {
            $checker = app($rule);
            if ($checker->allows($data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function explain($actor)
    {
        $role = $actor['role'] ?? 'guest';
        $privileged = Role::isPrivileged($role);

        return compact('role', 'privileged');
    }
}
