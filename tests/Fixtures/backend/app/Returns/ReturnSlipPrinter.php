<?php

namespace Shop\Returns;

use JesseGall\CodeCommandments\Sins\Backend\DerivedArgument;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Constructs the slip from four pieces of one return request. A constructor is a call site like any
 * other, and this one is being handed the request four times over.
 */
final class ReturnSlipPrinter
{
    #[Sinful(DerivedArgument::class)]
    public function print(ReturnRequest $request): ReturnSlip
    {
        return new ReturnSlip(
            $request->reference(),
            $request->reason(),
            $request->itemCount(),
        );
    }

    /**
     * Righteous twin: the constructor asks for the request itself, so nothing was flattened on the way
     * in — a parameter already typed as the subject has nothing to move.
     */
    #[Righteous(DerivedArgument::class)]
    public function printWhole(ReturnRequest $request): ReturnSlip
    {
        return ReturnSlip::of($request);
    }
}
