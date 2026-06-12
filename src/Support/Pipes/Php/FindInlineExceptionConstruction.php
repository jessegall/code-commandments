<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Find `throw new ...` sites that assemble the exception at the call
 * site — generic SPL exceptions, and named exceptions fed a message
 * string built where they are thrown.
 *
 * The righteous form is a named domain exception with a static factory
 * (`throw MissingRequiredInputException::for($port, $nodeId)`): the
 * type is catchable by name and the message has exactly one home.
 *
 * Exempt: `new self(...)` / `new static(...)` inside the exception's
 * own factories, and named exceptions receiving domain values instead
 * of a message string.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindInlineExceptionConstruction implements Pipe
{
    /**
     * Generic SPL exception/error classes — throwing one of these names
     * a failure category, never the actual failure.
     */
    private const GENERIC_EXCEPTIONS = [
        'Exception', 'Error',
        'RuntimeException', 'LogicException',
        'InvalidArgumentException', 'DomainException',
        'OutOfBoundsException', 'OutOfRangeException',
        'LengthException', 'RangeException',
        'UnexpectedValueException',
        'BadMethodCallException', 'BadFunctionCallException',
        'UnderflowException', 'OverflowException',
        'ErrorException', 'TypeError', 'ValueError',
        'ArithmeticError', 'DivisionByZeroError',
    ];

    /**
     * Functions that build a message string from parts.
     */
    private const MESSAGE_BUILDERS = ['sprintf', 'vsprintf', 'implode', 'json_encode'];

    /** @var list<string> */
    private array $allowed = [];

    /**
     * Exception names (short or fully qualified) the consumer accepts
     * being thrown inline.
     *
     * @param  list<string>  $allowed
     */
    public function withAllowed(array $allowed): self
    {
        $this->allowed = $allowed;

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $parents = $this->buildParentMap($input->ast);

        $nodeFinder = new NodeFinder;
        /** @var array<Expr\Throw_> $throws */
        $throws = $nodeFinder->findInstanceOf($input->ast, Expr\Throw_::class);

        $matches = [];

        foreach ($throws as $throw) {
            $new = $throw->expr;

            if (! $new instanceof Expr\New_ || ! $new->class instanceof Node\Name) {
                continue; // Rethrows, variables, and dynamic classes are not call-site construction.
            }

            $rawName = $new->class->toString();

            if (in_array(strtolower($rawName), ['self', 'static', 'parent'], true)) {
                continue; // The exception's own factory is the message's home.
            }

            $shortName = $this->shortName($rawName);
            $resolved = $input->useStatements[$rawName] ?? $rawName;

            if ($shortName === $this->enclosingClassName($throw, $parents)) {
                continue; // Factory constructing its own class by name.
            }

            if ($this->isAllowed($shortName, $resolved)) {
                continue;
            }

            $isGeneric = $this->isGenericException($resolved);
            $firstArg = $this->firstArgument($new);
            $messageIsString = $firstArg !== null && $this->isStringExpression($firstArg);

            if (! $isGeneric && ! $messageIsString) {
                continue; // Named exception fed domain values — acceptable, though a factory reads better.
            }

            $line = $throw->getStartLine();

            $matches[] = new MatchResult(
                name: $shortName,
                pattern: '',
                match: 'new ' . $shortName,
                line: $line,
                offset: null,
                content: $this->getSnippet($input->content, $line),
                groups: [
                    'kind' => $isGeneric ? 'generic' : 'custom_message',
                    'exception' => $shortName,
                    'method' => $this->enclosingFunctionLabel($throw, $parents),
                    'suggested' => $this->suggestedExceptionName($firstArg) ?? ($isGeneric ? null : $shortName) ?? 'a named exception',
                ],
            );
        }

        return $input->with(matches: $matches);
    }

    private function isGenericException(string $resolved): bool
    {
        return in_array(ltrim($resolved, '\\'), self::GENERIC_EXCEPTIONS, true);
    }

    private function isAllowed(string $shortName, string $resolved): bool
    {
        foreach ($this->allowed as $allowed) {
            $allowed = ltrim($allowed, '\\');

            if ($shortName === $this->shortName($allowed) || ltrim($resolved, '\\') === $allowed) {
                return true;
            }
        }

        return false;
    }

    private function firstArgument(Expr\New_ $new): ?Node\Arg
    {
        foreach ($new->args as $arg) {
            if ($arg instanceof Node\Arg) {
                return $arg->name === null || $arg->name->toString() === 'message' ? $arg : null;
            }
        }

        return null;
    }

    /**
     * Whether the expression is a message string — a literal, an
     * interpolated string, a concatenation, or a builder call.
     */
    private function isStringExpression(Node\Arg $arg): bool
    {
        $expr = $arg->value;

        if ($expr instanceof Scalar\String_ || $expr instanceof Scalar\InterpolatedString) {
            return true;
        }

        if ($expr instanceof Expr\BinaryOp\Concat) {
            return true;
        }

        return $expr instanceof Expr\FuncCall
            && $expr->name instanceof Node\Name
            && in_array($expr->name->toString(), self::MESSAGE_BUILDERS, true);
    }

    /**
     * Derive a StudlyCase exception-class suggestion from the leading
     * words of the message — "Missing required input '{$name}'" becomes
     * MissingRequiredInputException.
     */
    private function suggestedExceptionName(?Node\Arg $arg): ?string
    {
        $text = $arg === null ? null : $this->leadingLiteralText($arg->value);
        $text = $text === null ? null : preg_replace('/%[a-zA-Z]/', '', $text);

        if ($text === null || preg_match_all('/[A-Za-z]{2,}/', $text, $m) === 0) {
            return null;
        }

        $words = array_slice($m[0], 0, 4);
        $studly = implode('', array_map(static fn (string $w) => ucfirst(strtolower($w)), $words));

        if ($studly === '') {
            return null;
        }

        return str_ends_with($studly, 'Exception') ? $studly : $studly . 'Exception';
    }

    private function leadingLiteralText(Expr $expr): ?string
    {
        if ($expr instanceof Scalar\String_) {
            return $expr->value;
        }

        if ($expr instanceof Scalar\InterpolatedString) {
            $first = $expr->parts[0] ?? null;

            return $first instanceof Node\InterpolatedStringPart ? $first->value : null;
        }

        if ($expr instanceof Expr\BinaryOp\Concat) {
            return $this->leadingLiteralText($expr->left);
        }

        if ($expr instanceof Expr\FuncCall
            && $expr->name instanceof Node\Name
            && in_array($expr->name->toString(), self::MESSAGE_BUILDERS, true)
            && isset($expr->args[0])
            && $expr->args[0] instanceof Node\Arg
        ) {
            return $this->leadingLiteralText($expr->args[0]->value);
        }

        return null;
    }

    private function shortName(string $name): string
    {
        return substr($name, (int) strrpos('\\' . $name, '\\'));
    }

    /**
     * @param  array<int, Node>  $parents
     */
    private function enclosingClassName(Node $node, array $parents): ?string
    {
        $current = $parents[spl_object_id($node)] ?? null;

        while ($current !== null) {
            if ($current instanceof Node\Stmt\ClassLike) {
                return $current->name?->toString();
            }

            $current = $parents[spl_object_id($current)] ?? null;
        }

        return null;
    }

    /**
     * @param  array<int, Node>  $parents
     */
    private function enclosingFunctionLabel(Node $node, array $parents): string
    {
        $current = $parents[spl_object_id($node)] ?? null;

        while ($current !== null) {
            if ($current instanceof Node\Stmt\ClassMethod || $current instanceof Node\Stmt\Function_) {
                return $current->name->toString();
            }

            $current = $parents[spl_object_id($current)] ?? null;
        }

        return '?';
    }

    /**
     * @param  array<Node>  $ast
     * @return array<int, Node>
     */
    private function buildParentMap(array $ast): array
    {
        $parents = [];

        $visitor = new class($parents) extends NodeVisitorAbstract {
            /** @var array<int, Node> */
            public array $parents;
            /** @var array<Node> */
            private array $stack = [];

            public function __construct(array &$parents)
            {
                $this->parents = &$parents;
            }

            public function enterNode(Node $node): ?int
            {
                $parent = end($this->stack);

                if ($parent !== false) {
                    $this->parents[spl_object_id($node)] = $parent;
                }

                $this->stack[] = $node;

                return null;
            }

            public function leaveNode(Node $node): ?int
            {
                array_pop($this->stack);

                return null;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->parents;
    }

    private function getSnippet(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
