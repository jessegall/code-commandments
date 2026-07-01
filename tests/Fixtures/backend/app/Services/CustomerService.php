<?php

namespace Shop\Services;

use JesseGall\CodeCommandments\Sins\Backend\DeNulledFinder;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\FacadeCall;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\RawRequestInput;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Customer;

final class CustomerService
{
    #[Sinful(DeNulledFinder::class)]
    public function find(string $email): ?Customer
    {
        return Customer::query()->where('email', $email)->first();
    }

    #[Sinful(RawRequestInput::class)]
    #[Sinful(FacadeCall::class)]
    public function greeting(Request $request): string
    {
        $customer = $this->find($request->input('email'));

        if ($customer === null) {
            return 'Hello, guest';
        }

        return 'Hello, ' . Cache::get('name:' . $customer->id, $customer->name);
    }

    public function isRegistered(string $email): bool
    {
        return $this->find($email) !== null;
    }
}
