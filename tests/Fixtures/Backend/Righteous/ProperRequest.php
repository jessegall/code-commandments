<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store(StoreUserRequest $request)
    {
        // Righteous: Using typed FormRequest getters
        return User::create([
            'name' => $request->getName(),
            'email' => $request->getEmail(),
            'password' => $request->getPassword(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        // Righteous: Using typed FormRequest getters
        return $user->update([
            'name' => $request->getName(),
            'email' => $request->getEmail(),
        ]);
    }
}
