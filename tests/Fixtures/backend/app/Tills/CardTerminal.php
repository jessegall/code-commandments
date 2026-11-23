<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\MatchDefaultReturnsNull;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The card terminal beside a till, naming the card scheme a payment came in on.
 */
final class CardTerminal
{
    /**
     * @var list<?string>
     */
    private array $schemes = [];

    #[Sinful(MatchDefaultReturnsNull::class)]
    public function record(string $prefix): int
    {
        $scheme = match ($prefix) {
            '4' => 'visa',
            '5' => 'mastercard',
            '3' => 'amex',
            default => null,
        };
        $this->schemes[] = $scheme;

        return count($this->schemes);
    }

    #[Righteous(MatchDefaultReturnsNull::class)]
    public function receiptLine(string $prefix, int $cents): string
    {
        $scheme = match ($prefix) {
            '4' => 'visa',
            '5' => 'mastercard',
            default => null,
        };
        if ($scheme === null) {
            return "card payment {$cents}";
        }

        return "{$scheme} payment {$cents}";
    }
}
