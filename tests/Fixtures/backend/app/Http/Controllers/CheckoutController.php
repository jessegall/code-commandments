<?php

namespace Shop\Http\Controllers;

use JesseGall\CodeCommandments\Sins\Backend\ContainerReach;
use JesseGall\CodeCommandments\Sins\Backend\RawRequestInput;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Services\PaymentProcessor;

class CheckoutController extends Controller
{
    #[Sinful(RawRequestInput::class)]
    #[Sinful(ContainerReach::class)]
    public function pay(Request $request): array
    {
        $processor = app(PaymentProcessor::class);
        $token = $request->input('token');

        $result = $processor->charge($token, (int) $request->input('amount'));

        return ['ok' => $result];
    }

    #[Righteous(ContainerReach::class)]
    public function payClean(PaymentProcessor $processor, string $token, int $amount): array
    {
        return ['ok' => $processor->charge($token, $amount)];
    }
}
