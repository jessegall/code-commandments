<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ConstructorOrchestration;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Data;

/**
 * Seeded through a `for()` factory, then a constructor that projects each slot from the injected
 * reader — pure `$this->slot = $this->cart->…()` fills that belong in `#[Computed]` hooks.
 */
final class TimelinePage extends Data
{
    public readonly CartLine $head;

    public readonly MenuLink $back;

    public static function for(string $order): self
    {
        return self::from(['order' => $order]);
    }

    #[Sinful(ConstructorOrchestration::class)]
    public function __construct(
        #[Hidden]
        #[FromContainer(CartReader::class)]
        public readonly CartReader $cart,
        public readonly string $order,
    ) {
        $this->head = $this->cart->firstLine();
        $this->back = $this->cart->backLink();
    }

    public function label(): string
    {
        return match ($this->head->qty) {
            0 => 'empty',
            1 => 'single',
            default => 'multiple',
        };
    }
}
