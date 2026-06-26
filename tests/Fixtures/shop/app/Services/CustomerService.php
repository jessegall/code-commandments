<?php

namespace Shop\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use JesseGall\CodeCommandments\Detectors\Backend\DeNulledFinderDetector;
use JesseGall\CodeCommandments\Detectors\Backend\FacadeCallDetector;
use JesseGall\CodeCommandments\Detectors\Backend\RawRequestInputDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Customer;

final class CustomerService
{
    #[Sinful(DeNulledFinderDetector::class)]
    public function find(string $email): ?Customer
    {
        return Customer::query()->where('email', $email)->first();
    }

    #[Sinful(RawRequestInputDetector::class)]
    #[Sinful(FacadeCallDetector::class)]
    public function greeting(Request $request): string
    {
        $customer = $this->find($request->input('email'));

        if ($customer === null) {
            return 'Hello, guest';
        }

        return 'Hello, ' . Cache::get('name:' . $customer->id, $customer->name);
    }
}
