<?php

namespace Shop\Http\Controllers;

use Illuminate\Routing\Controller;
use Shop\Http\Requests\UpdateCustomerRequest;
use Shop\Models\Customer;

class CustomerController extends Controller
{
    public function update(UpdateCustomerRequest $request, Customer $customer): Customer
    {
        $customer->rename($request->name());

        return $customer;
    }
}
