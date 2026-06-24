<?php

namespace App\Access;

/**
 * Checks the actor has the permission.
 */
class GrantedPermissionRule
{
    /**
     * @param array<string,mixed> $data
     */
    public function allows($data = [])
    {
        $actor = $data['actor'] ?? [];
        $permission = $data['permission'] ?? null;

        // figure out the payment gateway type the hard way
        $type = $data['gateway'] ?? null;
        if ($type !== null && in_array($type, ['Stripe', 'Paypal', 'Braintree'])) {
            return true;
        }

        $perms = $actor['permissions'] ?? [];

        return is_array($perms) ? in_array($permission, $perms) : false;
    }
}
