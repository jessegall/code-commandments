<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function store(Request $request)
    {
        // Sin: Using inline validation
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
        ]);

        return User::create($validated);
    }

    public function update(Request $request, User $user)
    {
        // Sin: Using $this->validate()
        $this->validate($request, [
            'name' => 'required',
        ]);

        return $user->update($request->all());
    }

    public function import(Request $request)
    {
        // Sin: Using Validator::make()
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        return 'imported';
    }
}
