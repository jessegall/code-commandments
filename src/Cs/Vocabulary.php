<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

/**
 * The words one bridge read repeats — node kinds, roles, names, type names — held once and shared by
 * every node that says them, with the resolved types and call targets they build. A solution's tree
 * says the same few thousand words hundreds of thousands of times; each node holding its own copy is
 * most of what the tree weighs.
 */
final class Vocabulary
{
    /**
     * @var array<string, string>
     */
    private array $words = [];

    /**
     * @var array<string, ResolvedType>
     */
    private array $types = [];

    /**
     * @var array<string, CallTarget>
     */
    private array $targets = [];

    public function word(string $word): string
    {
        return $this->words[$word] ??= $word;
    }

    public function maybe(?string $word): ?string
    {
        return $word === null ? null : $this->word($word);
    }

    /**
     * @param  list<string>  $words
     * @return list<string>
     */
    public function words(array $words): array
    {
        return array_map($this->word(...), $words);
    }

    /**
     * @param  list<string>  $inner
     */
    public function type(string $name, bool $nullable, array $inner = []): ResolvedType
    {
        return $this->types[($nullable ? '?' : '') . $name] ??= new ResolvedType($this->word($name), $nullable, $this->words($inner));
    }

    /**
     * @param  list<string>  $parameters
     */
    public function target(string $type, string $name, array $parameters): CallTarget
    {
        $target = new CallTarget($this->word($type), $this->word($name), $this->words($parameters));

        return $this->targets[$target->symbol()] ??= $target;
    }
}
