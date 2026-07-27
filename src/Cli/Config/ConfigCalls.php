<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Config;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\Expression;

/**
 * The `$config->…(…)` calls a project's `config.php` declares, read through the AST — the ONE
 * home of "find me the `paths()` call" for everything that edits the file. {@see ConfigFile} (the
 * disable list, the layer chain, the registered detectors) and {@see ConfigScribe} (the scaffold,
 * the plan-execution block) both compose it, so there is a single answer to what counts as a call
 * and a single place where the config is parsed rather than scanned.
 */
final class ConfigCalls
{
    public function __construct(private readonly string $path) {}

    /**
     * The first `$config-><method>(...)` call in the config, or null when it declares none (or
     * there is no config file at all).
     */
    public function named(string $method): ?MethodCall
    {
        return $this->all($method)[0] ?? null;
    }

    /**
     * The `$config-><method>(...)` call, or the failure — for a caller that cannot proceed without
     * it (the `disable()` list the scaffold always ships). The resolve-or-throw twin of
     * {@see named}, so no caller has to de-null a find of its own.
     */
    public function require(string $method): MethodCall
    {
        return $this->named($method) ?? throw UnrecognizableConfig::at($this->path);
    }

    /**
     * Is that call declared?
     */
    public function has(string $method): bool
    {
        return $this->all($method) !== [];
    }

    /**
     * EVERY `$config-><method>(...)` call, in the order the AST yields them — the chained ones
     * (`->layer(...)->layer(...)`) come outermost first, which is the LAST link written.
     *
     * @return list<MethodCall>
     */
    public function all(string $method): array
    {
        $calls = [];

        foreach ($this->matches($method) as $match) {
            if ($match->node instanceof MethodCall) {
                $calls[] = $match->node;
            }
        }

        return $calls;
    }

    /**
     * Every `$config->…;` STATEMENT in the config, in source order — what an edit anchors a new
     * declaration to. A chained call contributes its statement once.
     *
     * @return list<Expression>
     */
    public function statements(): array
    {
        $statements = [];

        foreach ($this->matches() as $match) {
            $statement = $match->parent()->node;

            if ($statement instanceof Expression) {
                $statements[$statement->getStartFilePos()] = $statement;
            }
        }

        ksort($statements);

        return array_values($statements);
    }

    /**
     * The method-call matches in the config — all of them, or only those named $method. The ONE
     * place the config file is parsed.
     *
     * @return list<NodeMatch>
     */
    private function matches(?string $method = null): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $names = $method === null ? [] : [$method];

        return Codebase::fromString((string) file_get_contents($this->path), $this->path)->whereMethod(...$names)->get();
    }
}
