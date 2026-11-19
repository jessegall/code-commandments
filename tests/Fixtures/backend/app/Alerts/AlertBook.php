<?php

namespace Shop\Alerts;

use JesseGall\CodeCommandments\Sins\Backend\ListMirrorsFolder;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Alerts\Rules\LowStockRule;
use Shop\Alerts\Rules\HighRefundRule;
use Shop\Alerts\Rules\SlowDispatchRule;
use Shop\Alerts\Rules\ChargebackRule;
use Shop\Alerts\Rules\EmptyBasketRule;
use Shop\Alerts\Rules\PriceDropRule;

/**
 * The alert rules the shop checks, all of them by day and a chosen few by night.
 */
final class AlertBook
{
    /**
     * @return list<class-string<AlertRule>>
     */
    #[Sinful(ListMirrorsFolder::class)]
    public function daily(): array
    {
        return [
            LowStockRule::class,
            HighRefundRule::class,
            SlowDispatchRule::class,
            ChargebackRule::class,
            EmptyBasketRule::class,
            PriceDropRule::class,
        ];
    }

    /**
     * @return list<class-string<AlertRule>>
     */
    #[Righteous(ListMirrorsFolder::class)]
    public function nightly(): array
    {
        return [
            LowStockRule::class,
            HighRefundRule::class,
            SlowDispatchRule::class,
            ChargebackRule::class,
            EmptyBasketRule::class,
        ];
    }
}
