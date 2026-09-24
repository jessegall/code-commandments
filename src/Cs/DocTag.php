<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Support\Prose;

/**
 * One top-level part of a documentation comment — a tag such as `<summary>` or `<param>`, as written, or a run
 * of loose prose outside any tag, named {@see PROSE}.
 */
final readonly class DocTag
{
    /**
     * The name a run of prose outside any tag goes by.
     */
    public const string PROSE = '#text';

    /**
     * The tags that describe the signature itself — its summary and each part of it — rather than something
     * beyond it, such as what it throws.
     */
    private const array SIGNATURE_TAGS = ['summary', 'remarks', 'param', 'typeparam', 'returns', 'value'];

    public function __construct(
        public string $name,
        public string $xml,
    ) {}

    /**
     * Does this tag say what the member is, or describe a part of its signature?
     */
    public function isAboutTheSignature(): bool
    {
        return in_array($this->name, self::SIGNATURE_TAGS, true);
    }

    /**
     * Does this tag describe the member itself, rather than one part of its contract?
     */
    public function isDescription(): bool
    {
        return in_array($this->name, ['summary', 'remarks', self::PROSE], true);
    }

    /**
     * Does this tag say nothing beyond $words — empty, or made only of them?
     *
     * @param  list<string>  $words
     */
    public function saysNothingBeyond(array $words): bool
    {
        return array_diff(Prose::words(strip_tags($this->xml)), $words) === [];
    }
}
