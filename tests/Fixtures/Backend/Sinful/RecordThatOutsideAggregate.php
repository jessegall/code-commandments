<?php

namespace App\Http\Controllers;

use App\Aggregates\UserAggregate;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function store(StoreUserRequest $request)
    {
        $aggregate = UserAggregate::retrieve($request->uuid());

        // Sin: Calling recordThat from controller instead of inside aggregate
        $aggregate->recordThat(new UserCreated($request->getName()));

        $aggregate->persist();

        return response()->noContent();
    }
}
