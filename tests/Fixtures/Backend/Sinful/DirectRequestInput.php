<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function store(Request $request)
    {
        $data = ['name' => $request->getName()];

        // Sin: Using has() in controller
        if ($request->has('email')) {
            $data['email'] = $request->email;
        }

        // Sin: Using filled() in controller
        if ($request->filled('phone')) {
            $data['phone'] = $request->phone;
        }

        // Sin: Using input() in controller
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
