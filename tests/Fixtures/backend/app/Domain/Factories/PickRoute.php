<?php

namespace Shop\Domain\Factories;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedStaticFactory;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

final class PickRoute
{
    #[Sinful(RepeatedStaticFactory::class)]
    public function stop(string $written): string
    {
        return ShelfCode::parse($written)->aisle() . '/' . ShelfCode::parse($written)->slot();
    }

    #[Fixed(RepeatedStaticFactory::class)]
    public function stopOnce(string $written): string
    {
        $code = ShelfCode::parse($written);

        return $code->aisle() . '/' . $code->slot();
    }
}
