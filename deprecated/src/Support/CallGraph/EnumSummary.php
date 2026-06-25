<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\CallGraph;

/**
 * Distilled enum info retained by the CodebaseIndex.
 *
 * `cases` is keyed by backing value (for backed enums) or case name (for
 * unit enums) so the StringsThatShouldBeEnums pipe can look up
 * "does this literal correspond to a case?" in O(1).
 */
final readonly class EnumSummary
{
    /**
     * @param  array<string, string>  $cases  backing value (or case name for unit enums) => case name
     */
    public function __construct(
        public string $fqcn,
        public string $short,
        public ?string $backing,
        public array $cases,
        public string $filePath,
    ) {}
}
