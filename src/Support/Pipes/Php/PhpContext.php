<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use PhpParser\Node;

/**
 * Context object for PHP analysis pipelines.
 *
 * Provides type-safe access to pipeline data instead of array indexing.
 */
final class PhpContext
{
    public function __construct(
        public readonly string $filePath,
        public readonly string $content,
        public ?array $ast = null,
        /** @var array<Node\Stmt\Class_> */
        public array $classes = [],
        /** @var array<array{class: Node\Stmt\Class_, method: Node\Stmt\ClassMethod}> */
        public array $methods = [],
        /** @var array<array{class: Node\Stmt\Class_, method: Node\Stmt\ClassMethod, param: Node\Param, type: string, fqcn: string, name: string}> */
        public array $parameters = [],
        /** @var array<string, string> Short name => FQCN */
        public array $useStatements = [],
        public ?string $namespace = null,
        /** @var array<MatchResult> */
        public array $matches = [],
    ) {}

    /**
     * Create a new context from file path and content.
     */
    public static function from(string $filePath, string $content): self
    {
        return new self($filePath, $content);
    }

    /**
     * Create a copy with updated values.
     */
    public function with(
        ?array $ast = null,
        ?array $classes = null,
        ?array $methods = null,
        ?array $parameters = null,
        ?array $useStatements = null,
        ?string $namespace = null,
        ?array $matches = null,
    ): self {
        return new self(
            filePath: $this->filePath,
            content: $this->content,
            ast: $ast ?? $this->ast,
            classes: $classes ?? $this->classes,
            methods: $methods ?? $this->methods,
            parameters: $parameters ?? $this->parameters,
            useStatements: $useStatements ?? $this->useStatements,
            namespace: $namespace ?? $this->namespace,
            matches: $matches ?? $this->matches,
        );
    }

    /**
     * Check if AST parsing was successful.
     */
    public function hasAst(): bool
    {
        return $this->ast !== null && ! empty($this->ast);
    }

    /**
     * Check if any classes were found.
     */
    public function hasClasses(): bool
    {
        return ! empty($this->classes);
    }

    /**
     * Check if any methods were found.
     */
    public function hasMethods(): bool
    {
        return ! empty($this->methods);
    }

    /**
     * Check if any parameters were found.
     */
    public function hasParameters(): bool
    {
        return ! empty($this->parameters);
    }

    /**
     * Check if any matches were found.
     */
    public function hasMatches(): bool
    {
        return ! empty($this->matches);
    }

    /**
     * Get the first class name if available.
     */
    public function getClassName(): ?string
    {
        if (empty($this->classes)) {
            return null;
        }

        return $this->classes[0]->name?->toString();
    }

    /**
     * Check if file path contains a string.
     */
    public function filePathContains(string $needle): bool
    {
        return str_contains($this->filePath, $needle);
    }

    /**
     * Check if this is a file that belongs to the code-commandments package.
     *
     * These files (Prophets, Validators) contain example code in heredocs
     * that should not be flagged by the prophets themselves.
     */
    public function isPackageFile(): bool
    {
        return $this->filePathContains('Commandments/Validators/')
            || $this->filePathContains('Prophets/');
    }
}
