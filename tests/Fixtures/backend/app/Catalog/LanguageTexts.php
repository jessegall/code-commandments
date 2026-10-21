<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\ImpossibleGuard;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Translations of one string, asked for by language. The first method answers false when the
 * language is null, which its own type says cannot happen: either the type is wrong and callers
 * do pass null, or nobody needed the check and every reader has to work that out. Below it the
 * repair, and the two look-alikes that are not the sin: a guard that asserts the contract and
 * dies, and one on a parameter typed as it really is.
 */
final class LanguageTexts
{
    /**
     * @param array<string, string> $texts
     */
    public function __construct(private readonly array $texts) {}

    #[Sinful(ImpossibleGuard::class)]
    public function hasTranslation(Language $language): bool
    {
        if ($language === null) {
            return false;
        }

        return isset($this->texts[$language->code]);
    }

    #[Fixed(ImpossibleGuard::class)]
    public function hasTextIn(Language $language): bool
    {
        return isset($this->texts[$language->code]);
    }

    #[Righteous(ImpossibleGuard::class)]
    public function textIn(Language $language): string
    {
        if ($language === null) {
            throw LanguageMissing::wanted();
        }

        return $this->texts[$language->code];
    }

    #[Righteous(ImpossibleGuard::class)]
    public function textOrPrimary(Language | null $language, string $primary): string
    {
        if ($language === null) {
            return $primary;
        }

        return $this->texts[$language->code] ?? $primary;
    }
}
