<?php

namespace Shop\Domain\Factories;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedStaticFactory;
use JesseGall\CodeCommandments\Testing\Righteous;

final class ShelfAudit
{
    #[Righteous(RepeatedStaticFactory::class)]
    public function either(string $written): string
    {
        if (str_ends_with($written, '0')) {
            return (string) ShelfCode::parse($written)->slot();
        }

        return ShelfCode::parse($written)->aisle();
    }

    #[Righteous(RepeatedStaticFactory::class)]
    public function moved(string $written, string $target): bool
    {
        $code = $written;
        $before = ShelfCode::parse($code)->aisle();
        $code = $target;

        return $before !== ShelfCode::parse($code)->aisle();
    }

    #[Righteous(RepeatedStaticFactory::class)]
    public function apart(string $from, string $to): bool
    {
        return ShelfCode::parse($from)->aisle() === ShelfCode::parse($to)->aisle();
    }

    #[Righteous(RepeatedStaticFactory::class)]
    public function described(string $written, \Closure $read): string
    {
        try {
            return $read($written);
        } catch (\InvalidArgumentException $error) {
            return sprintf('%s: %s', ShelfCode::parse($written)->aisle(), $error->getMessage());
        } catch (\RuntimeException $error) {
            return sprintf('%s failed: %s', ShelfCode::parse($written)->aisle(), $error->getMessage());
        }
    }
}
