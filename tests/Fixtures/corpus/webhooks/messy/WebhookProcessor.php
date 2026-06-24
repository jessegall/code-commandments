<?php

namespace App\Webhooks;

class WebhookProcessor
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function process(array $data)
    {
        $verifier = new SignatureVerifier();

        $ok = $verifier->verify($data, $data['signature'] ?? null);

        if (! $ok) {
            return ['status' => 'invalid_signature'];
        }

        $store = app(HandlerStore::class);

        $event = $data['event'] ?? '';
        $handler = $store->get($event);

        if ($handler === null) {
            if ($event === 'order.created') {
                $handler = new OrderCreatedHandler();
            } elseif ($event === 'payment.succeeded') {
                $handler = new PaymentHandler();
            } elseif ($event === 'payment.refunded') {
                $handler = new PaymentHandler();
            } else {
                return ['status' => 'no_handler'];
            }
        }

        $handler->handle($data);

        return ['status' => 'ok'];
    }
}
