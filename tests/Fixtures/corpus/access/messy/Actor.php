<?php

namespace App\Access;

/**
 * The actor. Just a bag of stuff really.
 */
class Actor
{
    public $id;
    public $role;
    public $permissions;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct($data = [])
    {
        $this->id = $data['id'] ?? null;
        $this->role = $data['role'] ?? 'guest';
        $this->permissions = $data['permissions'] ?? [];
    }

    public function holds($permission)
    {
        if (is_array($this->permissions)) {
            return in_array($permission, $this->permissions);
        }

        return false;
    }
}
