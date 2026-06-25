<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Http\Resources\UserCollection;
use Illuminate\Routing\Controller;

class ApiController extends Controller
{
    public function index()
    {
        // Righteous: Using API Resource collection
        return UserResource::collection(User::all());
    }

    public function show(User $user)
    {
        // Righteous: Using API Resource
        return new UserResource($user);
    }

    public function paginated()
    {
        // Righteous: Using API Resource with pagination
        return UserResource::collection(User::paginate());
    }
}
