<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Find auth user/id access patterns in PHP code.
 *
 * Detects:
 * - auth()->user() and auth()->id()
 * - auth('guard')->user() and auth('guard')->id()
 * - Auth::user() and Auth::id()
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindAuthUserCalls implements Pipe
{
    private const AUTH_METHODS = ['user', 'id'];

    public function handle(mixed $input): mixed
    {
        $matches = [];
        $nodeFinder = new NodeFinder;

        foreach ($input->classes as $class) {
            // Search in class methods
            foreach ($class->getMethods() as $method) {
                $this->findAuthCallsInStatements($method->stmts ?? [], $nodeFinder, $input, $matches);
            }

            // Search in property hooks (PHP 8.4+)
            foreach ($class->getProperties() as $property) {
                if (! empty($property->hooks)) {
                    foreach ($property->hooks as $hook) {
                        if ($hook instanceof Node\PropertyHook) {
                            $stmts = $hook->body instanceof Expr
                                ? [new Stmt\Expression($hook->body)]
                                : ($hook->body ?? []);
                            $this->findAuthCallsInStatements($stmts, $nodeFinder, $input, $matches);
                        }
                    }
                }
            }
        }

        return $input->with(matches: $matches);
    }

    /**
     * Find auth calls in a set of statements.
     *
     * @param  array<Node>  $stmts
     * @param  array<MatchResult>  $matches
     */
    private function findAuthCallsInStatements(array $stmts, NodeFinder $nodeFinder, PhpContext $input, array &$matches): void
    {
        /** @var array<Expr\MethodCall> $methodCalls */
        $methodCalls = $nodeFinder->findInstanceOf($stmts, Expr\MethodCall::class);

        foreach ($methodCalls as $call) {
            if ($this->isAuthHelperMethodCall($call)) {
                $matches[] = $this->createMatch($call, $input->content, 'auth()');
            }
        }

        /** @var array<Expr\StaticCall> $staticCalls */
        $staticCalls = $nodeFinder->findInstanceOf($stmts, Expr\StaticCall::class);

        foreach ($staticCalls as $call) {
            if ($this->isAuthFacadeCall($call, $input->useStatements)) {
                $matches[] = $this->createMatch($call, $input->content, 'Auth');
            }
        }
    }

    /**
     * Check if the method call is auth()->user() or auth()->id() or auth('guard')->user()/id().
     */
    private function isAuthHelperMethodCall(Expr\MethodCall $call): bool
    {
        if (! $call->name instanceof Node\Identifier) {
            return false;
        }

        $methodName = $call->name->toString();

        if (! in_array($methodName, self::AUTH_METHODS, true)) {
            return false;
        }

        // Check if the target is auth() or auth('guard')
        return $this->isAuthHelperFunctionCall($call->var);
    }

    /**
     * Check if the expression is an auth() helper function call.
     */
    private function isAuthHelperFunctionCall(Expr $expr): bool
    {
        if (! $expr instanceof Expr\FuncCall) {
            return false;
        }

        if (! $expr->name instanceof Node\Name) {
            return false;
        }

        return $expr->name->toString() === 'auth';
    }

    /**
     * Check if the static call is Auth::user() or Auth::id().
     *
     * @param  array<string, string>  $useStatements
     */
    private function isAuthFacadeCall(Expr\StaticCall $call, array $useStatements): bool
    {
        if (! $call->name instanceof Node\Identifier) {
            return false;
        }

        $methodName = $call->name->toString();

        if (! in_array($methodName, self::AUTH_METHODS, true)) {
            return false;
        }

        if (! $call->class instanceof Node\Name) {
            return false;
        }

        $className = $call->class->toString();

        // Check for direct Auth usage
        if ($className === 'Auth') {
            return true;
        }

        // Check if Auth is imported via use statement
        if (isset($useStatements['Auth'])) {
            $fqcn = $useStatements['Auth'];

            return $fqcn === 'Illuminate\\Support\\Facades\\Auth'
                || str_ends_with($fqcn, '\\Auth');
        }

        return false;
    }

    private function createMatch(Expr\MethodCall|Expr\StaticCall $call, string $content, string $source): MatchResult
    {
        $methodName = $call->name instanceof Node\Identifier
            ? $call->name->toString()
            : 'unknown';

        $line = $call->getStartLine();

        return new MatchResult(
            name: $source . '::' . $methodName . '()',
            pattern: '',
            match: $source . '->' . $methodName . '()',
            line: $line,
            offset: null,
            content: $this->getSnippet($content, $line),
            groups: [$source, $methodName],
        );
    }

    private function getSnippet(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
