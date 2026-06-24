<?php

namespace App\Webhooks;

use Illuminate\Http\RedirectResponse;

/**
 * Receives an inbound webhook delivery and hands it to the processor.
 */
final class WebhookController
{
    public function __construct(
        private readonly WebhookProcessor $processor,
    ) {}

    public function store(WebhookRequest $request): RedirectResponse
    {
        $this->processor->process(
            body: $request->getContent(),
            signature: $request->signature(),
            payload: $request->payload(),
        );

        return redirect()->route('webhooks.index');
    }
}
