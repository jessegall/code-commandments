<?php

namespace Shop\Customers;

use JesseGall\CodeCommandments\Sins\Backend\PhpTypes\OptionAsNullable;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use JesseGall\PhpTypes\Option;

/**
 * Returns `?Option` — an Option already models absence, so nesting it in a
 * nullable is a null wearing an Option costume. The honest twin returns a plain
 * `Option`.
 */
final class CustomerLocator
{
    #[Sinful(OptionAsNullable::class)]
    public function locate(string $email): ?Option
    {
        return Option::none();
    }

    #[Righteous(OptionAsNullable::class)]
    public function locateHonestly(string $email): Option
    {
        return Option::fromNullable($email === '' ? null : $email);
    }
}
