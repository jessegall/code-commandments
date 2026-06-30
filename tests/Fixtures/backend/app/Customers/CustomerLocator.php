<?php

namespace Shop\Customers;

use JesseGall\CodeCommandments\Detectors\Backend\OptionAsNullableDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use JesseGall\PhpTypes\Option;

/**
 * Returns `?Option` — an Option already models absence, so nesting it in a
 * nullable is a null wearing an Option costume. The honest twin returns a plain
 * `Option`.
 */
final class CustomerLocator
{
    #[Sinful(OptionAsNullableDetector::class)]
    public function locate(string $email): ?Option
    {
        return Option::none();
    }

    public function locateHonestly(string $email): Option
    {
        return Option::fromNullable($email === '' ? null : $email);
    }
}
