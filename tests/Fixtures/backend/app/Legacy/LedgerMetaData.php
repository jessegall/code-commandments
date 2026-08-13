<?php

namespace Shop\Legacy;

use JesseGall\CodeCommandments\Sins\Backend\ConstClassEnum;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\EventSourcing\Enums\MetaData;

/**
 * A bag of scalar constants that CANNOT become an enum: PHP enums extend nothing, and this one's
 * base class is a package's contract for metadata keys. The fix the rule teaches does not exist
 * here, so the finding could only ever be reported and never obeyed.
 */
#[Righteous(ConstClassEnum::class)]
class LedgerMetaData extends MetaData
{

    public const string CASHIER_ID = 'cashier-id';

    public const string PACKED_BY = 'packed-by';

    public const string SHOP_ID = 'shop-id';

}
