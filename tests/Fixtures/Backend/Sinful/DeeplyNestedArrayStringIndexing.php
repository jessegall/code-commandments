<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful;

/**
 * Shows a tree of nested structured arrays. Every level of nesting should
 * become its own DTO — this file stresses the "wrap each level" hint.
 */
class DeeplyNestedArrayStringIndexing
{
    public function customerName(array $data): string
    {
        return $data['order']['customer']['name'];
    }

    public function firstLineItemSku(array $data): string
    {
        return $data['order']['lines'][0]['product']['sku'];
    }

    public function paymentMethodLabel(array $data): string
    {
        return $data['order']['payment']['method']['label'];
    }
}
