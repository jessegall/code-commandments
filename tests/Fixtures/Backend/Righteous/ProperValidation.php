<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    // Righteous: Using FormRequest for validation
    public function store(StoreUserRequest $request)
    {
        return User::create([
            'name' => $request->getName(),
            'email' => $request->getEmail(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        return $user->update([
            'name' => $request->getName(),
        ]);
    }
}
