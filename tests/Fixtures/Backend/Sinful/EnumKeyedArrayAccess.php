<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful;

enum OrderField: string
{
    case Total = 'total';
    case Subtotal = 'subtotal';
    case Tax = 'tax';
}

class OrderFieldConstants
{
    public const TOTAL = 'total';
    public const SUBTOTAL = 'subtotal';
}

/**
 * Uses enum cases' `->value` or string class constants as array keys. This
 * is the array-as-struct pattern wearing a typed-key disguise — the fix is
 * to have a typed accessor that hides `->value`.
 */
class EnumKeyedArrayAccess
{
    public function totalFromBackedValue(array $row): mixed
    {
        return $row[OrderField::Total->value];
    }

    public function subtotalFromClassConst(array $row): mixed
    {
        return $row[OrderFieldConstants::SUBTOTAL];
    }

    public function taxFromSelfConst(array $row): mixed
    {
        return $row[self::TAX_FIELD];
    }

    private const TAX_FIELD = 'tax';
}
