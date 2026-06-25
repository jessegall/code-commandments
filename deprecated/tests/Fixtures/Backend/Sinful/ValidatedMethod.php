<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store(StoreUserRequest $request)
    {
        // Sin: Using validated() to get all data as array
        $data = $request->validated();

        return User::create($data);
    }

    public function update(StoreUserRequest $request, User $user)
    {
        // Sin: Using safe()->all()
        $data = $request->safe()->all();

        return $user->update($data);
    }

    public function patch(StoreUserRequest $request, User $user)
    {
        // Sin: Using safe()->only()
        $data = $request->safe()->only(['name', 'email']);

        return $user->update($data);
    }
}
