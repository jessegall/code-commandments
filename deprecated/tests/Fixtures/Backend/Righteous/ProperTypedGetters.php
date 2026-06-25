<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    // Righteous: Using typed getters from FormRequest
    public function store(StoreUserRequest $request)
    {
        return User::create([
            'name' => $request->getName(),
            'email' => $request->getEmail(),
            'password' => $request->getPassword(),
        ]);
    }
}
