<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Find static method calls on Eloquent models that should use ::query() instead.
 *
 * Detects patterns like User::where(...) and flags them as sins,
 * requiring User::query()->where(...) instead.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindDirectStaticModelCalls implements Pipe
{
    /** @var array<string> */
    private array $forbiddenMethods;

    /**
     * @param  array<string>  $forbiddenMethods  Method names to flag
     */
    public function __construct(array $forbiddenMethods)
    {
        $this->forbiddenMethods = $forbiddenMethods;
    }

    public function handle(mixed $input): mixed
    {
        $matches = [];
        $nodeFinder = new NodeFinder;

        /** @var array<Expr\StaticCall> $staticCalls */
        $staticCalls = $nodeFinder->findInstanceOf($input->ast, Expr\StaticCall::class);

        foreach ($staticCalls as $call) {
            if (! $call->name instanceof Node\Identifier) {
                continue;
            }

            $methodName = $call->name->toString();

            if (! in_array($methodName, $this->forbiddenMethods, true)) {
                continue;
            }

            $className = $this->resolveClassName($call->class, $input->useStatements, $input->namespace);

            if ($className === null) {
                continue;
            }

            if (! TypeChecker::isModelType($className)) {
                continue;
            }

            $line = $call->getStartLine();

            $matches[] = new MatchResult(
                name: $methodName,
                pattern: '',
                match: $methodName,
                line: $line,
                offset: null,
                content: $this->getSnippet($input->content, $line),
                groups: [$methodName],
            );
        }

        return $input->with(matches: $matches);
    }

    private function resolveClassName(Node $class, array $useStatements, ?string $namespace): ?string
    {
        if (! $class instanceof Node\Name) {
            return null;
        }

        $name = $class->toString();

        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $parts = explode('\\', $name);
        $firstPart = $parts[0];

        if (isset($useStatements[$firstPart])) {
            if (count($parts) === 1) {
                return $useStatements[$firstPart];
            }

            $parts[0] = $useStatements[$firstPart];

            return implode('\\', $parts);
        }

        if ($namespace) {
            return $namespace . '\\' . $name;
        }

        return $name;
    }

    private function getSnippet(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
