<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    private StoreUserRequest $request;

    public function __construct(StoreUserRequest $request)
    {
        $this->request = $request;
    }

    public function store(StoreUserRequest $request)
    {
        $data = ['name' => $request->getName()];

        // Sin: Using has() on FormRequest
        if ($request->has('email')) {
            $data['email'] = $request->email;
        }

        // Sin: Using filled() on FormRequest
        if ($request->filled('phone')) {
            $data['phone'] = $request->phone;
        }

        // Sin: Using input() on FormRequest
        $name = $request->input('name');

        return User::create($data);
    }

    public function index()
    {
        // Sin: Using input() via $this->request property
        $window = $this->request->input('movementWindow', '30d');

        // Sin: Using has() via $this->request property
        if ($this->request->has('filter')) {
            return 'filtered';
        }

        return $window;
    }
}
