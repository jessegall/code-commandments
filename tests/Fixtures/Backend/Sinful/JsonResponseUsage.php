<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApiController extends Controller
{
    public function index()
    {
        // Sin: Using response()->json()
        return response()->json([
            'users' => User::all(),
        ]);
    }

    public function show(User $user)
    {
        // Sin: Using new JsonResponse()
        return new JsonResponse([
            'user' => $user->toArray(),
        ]);
    }

    public function stats()
    {
        // Sin: Using Response::json()
        return \Illuminate\Support\Facades\Response::json([
            'count' => User::count(),
        ]);
    }
}
