<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The languages a project writes. Empty means all of them, which is what a project that has said
 * nothing gets — so a scan asks {@see writes} without caring whether anything was configured.
 */
final class Languages
{
    /**
     * @var list<Language>
     */
    private readonly array $disabled;

    public function __construct(Language ...$disabled)
    {
        $this->disabled = array_values($disabled);
    }

    /**
     * The set a project's configuration describes.
     */
    public static function from(Config $config): self
    {
        return new self(...$config->disabledLanguages());
    }

    public function writes(Language $language): bool
    {
        return ! in_array($language, $this->disabled, true);
    }

    /**
     * @return list<Language>
     */
    public function disabled(): array
    {
        return $this->disabled;
    }
}
