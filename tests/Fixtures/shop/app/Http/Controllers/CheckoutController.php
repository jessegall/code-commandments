<?php

namespace Shop\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use JesseGall\CodeCommandments\Detectors\Backend\RawRequestInputDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Services\PaymentProcessor;

class CheckoutController extends Controller
{
    #[Sinful(RawRequestInputDetector::class)]
    public function pay(Request $request): array
    {
        $processor = app(PaymentProcessor::class);
        $token = $request->input('token');

        $result = $processor->charge($token, (int) $request->input('amount'));

        return ['ok' => $result];
    }
}
