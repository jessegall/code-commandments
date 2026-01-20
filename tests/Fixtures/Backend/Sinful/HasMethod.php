<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $data = ['name' => $request->name];

        // Sin: Using has() in controller
        if ($request->has('email')) {
            $data['email'] = $request->email;
        }

        // Sin: Using filled() in controller
        if ($request->filled('phone')) {
            $data['phone'] = $request->phone;
        }

        // Sin: Using missing() in controller
        if ($request->missing('role')) {
            $data['role'] = 'user';
        }

        // Sin: Using hasAny() in controller
        if ($request->hasAny(['avatar', 'photo'])) {
            $data['has_image'] = true;
        }

        return User::create($data);
    }
}
