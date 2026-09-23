<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

/**
 * What one request to the bridge answered: the files it wrote, and how much of them the compiler
 * resolved.
 */
final readonly class BridgeRead
{
    /**
     * @param  list<WrittenFile>  $files
     */
    public function __construct(
        public array $files,
        public Resolution $resolution,
    ) {}

    /**
     * @param  array<string, mixed>  $document  a response as the bridge's contract writes it
     */
    public static function fromContract(array $document): self
    {
        return new self(
            array_map(WrittenFile::fromContract(...), $document['files']),
            new Resolution((int) $document['resolution']['calls'], (int) $document['resolution']['resolved']),
        );
    }
}
