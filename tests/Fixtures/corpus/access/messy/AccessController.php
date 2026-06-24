<?php

namespace App\Access;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles the access check endpoint.
 */
class AccessController
{
    public function check(Request $request)
    {
        // validate by hand
        $actorId = $request->input('actor_id');
        if ($actorId === null || ! is_string($actorId)) {
            return response()->json(['error' => 'actor_id required'], 422);
        }

        $permission = $request->input('permission');
        if (empty($permission)) {
            return response()->json(['error' => 'permission required'], 422);
        }

        $role = $request->input('role', 'guest');
        $perms = $request->input('permissions', []);
        if (! is_array($perms)) {
            $perms = [$perms];
        }

        $limit = (int) ($request->input('limit') ?? 10);

        // load the actor
        $row = DB::table('actors')->where('id', $actorId)->first();
        $status = $row->status ?? 'pending';

        // business logic right here
        $allowed = false;
        if ($role == 'admin') {
            $allowed = true;
        } elseif ($status === 'active' && in_array($permission, $perms)) {
            $allowed = true;
        } elseif (in_array($role, ['editor', 'manager'])) {
            $allowed = $permission != 'users.manage';
        }

        $resolver = new AccessPolicyResolver();
        $extra = $resolver->allows([
            'actor' => ['id' => $actorId, 'role' => $role, 'permissions' => $perms],
            'permission' => $permission,
        ]);

        Log::info('access decided', ['actor' => $actorId, 'allowed' => $allowed || $extra]);

        return response()->json([
            'actor' => $actorId,
            'permission' => $permission,
            'allowed' => $allowed || $extra,
            'limit' => $limit,
        ]);
    }
}
