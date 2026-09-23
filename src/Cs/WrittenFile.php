<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

/**
 * One file the bridge wrote: its path with links resolved, how many syntax errors the compiler found in
 * it, whether its project is a test project, and its tree.
 */
final readonly class WrittenFile
{
    public function __construct(
        public string $path,
        public int $errors,
        public bool $test,
        public Node $root,
    ) {}

    /**
     * @param  array<string, mixed>  $written  a file as the bridge's contract writes it
     */
    public static function fromContract(array $written, Vocabulary $vocabulary): self
    {
        return new self((string) $written['path'], (int) $written['errors'], array_key_exists('test', $written), Node::fromBridge($written['root'], $vocabulary));
    }
}
