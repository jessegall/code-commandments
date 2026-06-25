<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    // Sin: Using raw Illuminate\Http\Request instead of FormRequest
    public function store(Request $request)
    {
        $name = $request->input('name');
        return User::create(['name' => $name]);
    }

    // Sin: Also using raw Request
    public function update(Request $request, User $user)
    {
        $data = $request->all();
        return $user->update($data);
    }
}
