<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Results;

/**
 * The result of attempting to repent (auto-fix) sins in a file.
 */
final class RepentanceResult
{
    /**
     * @param bool $absolved Whether the sins were successfully absolved
     * @param string|null $newContent The transformed content (if absolved)
     * @param array<string> $penance Description of what was done to seek absolution
     * @param string|null $blessing Path to backup file (if created)
     * @param string|null $failureReason Why absolution failed (if not absolved)
     * @param array<string, string> $createdFiles New files to write (absolute path => content), e.g. a generated enum class
     */
    public function __construct(
        public readonly bool $absolved,
        public readonly ?string $newContent = null,
        public readonly array $penance = [],
        public readonly ?string $blessing = null,
        public readonly ?string $failureReason = null,
        public readonly array $createdFiles = [],
    ) {}

    /**
     * Create a successful repentance result.
     *
     * @param string $newContent The fixed content
     * @param array<string> $penance What was done
     * @param string|null $blessing Backup path
     * @param array<string, string> $createdFiles New files to write (absolute path => content)
     */
    public static function absolved(string $newContent, array $penance = [], ?string $blessing = null, array $createdFiles = []): self
    {
        return new self(
            absolved: true,
            newContent: $newContent,
            penance: $penance,
            blessing: $blessing,
            createdFiles: $createdFiles,
        );
    }

    /**
     * Create a failed repentance result.
     */
    public static function unrepentant(string $reason): self
    {
        return new self(
            absolved: false,
            failureReason: $reason,
        );
    }

    /**
     * Create a result indicating no repentance was needed.
     */
    public static function alreadyRighteous(): self
    {
        return new self(
            absolved: true,
            penance: ['No sins found to absolve'],
        );
    }

    /**
     * Create a result indicating no changes were made.
     */
    public static function unchanged(): self
    {
        return new self(
            absolved: false,
            failureReason: 'No changes made',
        );
    }
}
