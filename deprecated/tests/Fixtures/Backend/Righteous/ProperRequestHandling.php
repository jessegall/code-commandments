<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    // Righteous: Using typed FormRequest with nullable fields and defaults
    public function store(StoreUserRequest $request)
    {
        // No need for has() checks - FormRequest handles nullables with defaults
        return User::create([
            'name' => $request->getName(),
            'email' => $request->getEmail(),
            'phone' => $request->getPhone(), // Returns null if not provided
            'role' => $request->getRole(),   // Returns default 'user' if not provided
        ]);
    }
}
