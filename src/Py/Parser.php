<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Expr\Parser as ExprParser;
use JesseGall\CodeCommandments\Py\Node\AnnAssign;
use JesseGall\CodeCommandments\Py\Node\Assign;
use JesseGall\CodeCommandments\Py\Node\AugAssign;
use JesseGall\CodeCommandments\Py\Node\Block;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\ExceptHandler;
use JesseGall\CodeCommandments\Py\Node\ExprStmt;
use JesseGall\CodeCommandments\Py\Node\ForLoop;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\Node\IfStmt;
use JesseGall\CodeCommandments\Py\Node\Import;
use JesseGall\CodeCommandments\Py\Node\Jump;
use JesseGall\CodeCommandments\Py\Node\MatchCase;
use JesseGall\CodeCommandments\Py\Node\MatchStmt;
use JesseGall\CodeCommandments\Py\Node\Module;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\Node\Param;
use JesseGall\CodeCommandments\Py\Node\Raise;
use JesseGall\CodeCommandments\Py\Node\Return_;
use JesseGall\CodeCommandments\Py\Node\Simple;
use JesseGall\CodeCommandments\Py\Node\TryStmt;
use JesseGall\CodeCommandments\Py\Node\WhileLoop;
use JesseGall\CodeCommandments\Py\Node\With;

/**
 * Reads a Python module into its statement tree — every compound statement with the block it owns,
 * every simple statement with the expressions it holds, which the {@see ExprParser} reads off the same
 * {@see Cursor}. Total: a statement the grammar cannot read is stepped over to its line's end, so a
 * file always yields a module.
 */
final class Parser
{
    private const array AUGMENTED = ['+=', '-=', '*=', '/=', '//=', '%=', '**=', '@=', '&=', '|=', '^=', '>>=', '<<='];

    private readonly ExprParser $expressions;

    private function __construct(private readonly Cursor $cursor)
    {
        $this->expressions = new ExprParser($cursor);
    }

    /**
     * $baseOffset is where $source begins in its file, so every node reports a position in the file.
     */
    public static function module(string $source, int $baseOffset = 0): Module
    {
        $parser = new self(new Cursor(new Lexer()->tokenize($source), $baseOffset));
        $start = $parser->cursor->offset();

        return $parser->located(new Module($parser->statementsUntil(TokenKind::EndMarker)), $start);
    }

    /**
     * Statements until a token of $closer's kind — the end of the file, or the DEDENT closing a block.
     *
     * @return list<Node>
     */
    private function statementsUntil(TokenKind $closer): array
    {
        $body = [];

        while (! $this->cursor->peek()->is($closer) && ! $this->cursor->atEnd()) {
            $before = $this->cursor->mark();
            $body = [...$body, ...$this->statement()];

            if ($this->cursor->mark() === $before) {
                $this->cursor->advance(); // the total guarantee: every statement moves the cursor on
            }
        }

        return $body;
    }

    /**
     * One statement — or, for a line of simple statements joined by `;`, each of them.
     *
     * @return list<Node>
     */
    private function statement(): array
    {
        $token = $this->cursor->peek();

        if ($token->is(TokenKind::Newline) || $token->is(TokenKind::Indent) || $token->is(TokenKind::Dedent)) {
            $this->cursor->advance(); // a stray line end or indentation — nothing to read

            return [];
        }

        $start = $this->cursor->offset();
        $decorators = $this->decorators();
        $compound = $this->compound($decorators, $start);

        return $compound === null ? $this->simpleLine() : [$compound];
    }

    /**
     * The `@decorator` lines above a `def` or a `class`.
     *
     * @return list<Expr>
     */
    private function decorators(): array
    {
        $decorators = [];

        while ($this->cursor->advanceIfOp('@')) {
            $decorators[] = $this->expressions->expression();
            $this->endLine();
        }

        return $decorators;
    }

    /**
     * The compound statement under the cursor, or null when it opens a simple one.
     *
     * @param  list<Expr>  $decorators
     */
    private function compound(array $decorators, int $start): ?Node
    {
        $async = $this->cursor->atName('async') && $this->opensCompound($this->cursor->at(1)) ? $this->cursor->advance() : null;
        $token = $this->cursor->peek();

        $node = match (true) {
            $token->isName('def') => $this->function($decorators, $async !== null),
            $token->isName('class') => $this->class($decorators),
            $token->isName('if') => $this->if(),
            $token->isName('for') => $this->for($async !== null),
            $token->isName('while') => $this->while(),
            $token->isName('try') => $this->try(),
            $token->isName('with') => $this->with($async !== null),
            $token->isName('match') && $this->isAtMatchStatement() => $this->match(),
            default => null,
        };

        return $node === null ? null : $this->located($node, $start);
    }

    private function opensCompound(Token $token): bool
    {
        return $token->isName('def') || $token->isName('for') || $token->isName('with');
    }

    /**
     * Is this `match` the statement rather than a name — a subject, a `:` and an indented `case` below?
     */
    private function isAtMatchStatement(): bool
    {
        $mark = $this->cursor->mark();
        $this->cursor->advance();
        $opens = $this->expressions->isAtExpression();

        if ($opens) {
            $this->expressions->expressionList();
            $opens = $this->cursor->advanceIfOp(':')
                && $this->cursor->advance()->is(TokenKind::Newline)
                && $this->cursor->advance()->is(TokenKind::Indent)
                && $this->cursor->atName('case');
        }

        $this->cursor->rewind($mark);

        return $opens;
    }

    /**
     * @param  list<Expr>  $decorators
     */
    private function function(array $decorators, bool $async): FunctionDef
    {
        $this->cursor->advance(); // `def`
        $name = $this->cursor->advance()->value;
        $this->skipTypeParameters();
        $params = $this->parameters();
        $returns = $this->cursor->advanceIfOp('->') ? $this->expressions->test() : null;

        return new FunctionDef($name, $params, $this->block(), $returns, $decorators, $async);
    }

    /**
     * The parameter list in parentheses — `/` and a bare `*` are markers, not parameters.
     *
     * @return list<Param>
     */
    private function parameters(): array
    {
        $params = [];

        if (! $this->cursor->advanceIfOp('(')) {
            return $params;
        }

        while ($this->cursor->isBefore(')')) {
            $param = $this->parameter();

            if ($param !== null) {
                $params[] = $param;
            }

            if (! $this->cursor->advanceIfOp(',')) {
                break;
            }
        }

        $this->cursor->advanceIfOp(')');

        return $params;
    }

    private function parameter(): ?Param
    {
        $start = $this->cursor->offset();
        $kind = $this->cursor->atOp('*') || $this->cursor->atOp('**') ? $this->cursor->advance()->value : '';

        if (! $this->cursor->peek()->isName()) {
            $this->cursor->advanceIfOp('/');

            return null; // a `/` or bare `*` marker
        }

        $name = $this->cursor->advance()->value;
        $annotation = $this->cursor->advanceIfOp(':') ? $this->expressions->test() : null;
        $default = $this->cursor->advanceIfOp('=') ? $this->expressions->test() : null;

        return $this->located(new Param($name, $kind, $annotation, $default), $start);
    }

    /**
     * @param  list<Expr>  $decorators
     */
    private function class(array $decorators): ClassDef
    {
        $this->cursor->advance(); // `class`
        $name = $this->cursor->advance()->value;
        $this->skipTypeParameters();
        $bases = [];

        if ($this->cursor->atOp('(')) {
            $bases = $this->expressions->callArguments();
        }

        return new ClassDef($name, $bases, $this->block(), $decorators);
    }

    /**
     * A PEP 695 `[T, U]` type-parameter list after a function or class name — stepped over.
     */
    private function skipTypeParameters(): void
    {
        if ($this->cursor->atOp('[')) {
            $this->cursor->skipGroup();
        }
    }

    private function if(): IfStmt
    {
        $start = $this->cursor->offset();
        $this->cursor->advance(); // `if` or `elif`
        $test = $this->expressions->expression();
        $body = $this->block();

        return $this->located(new IfStmt($test, $body, $this->elseOf()), $start);
    }

    /**
     * What follows an `if` block: an `elif` (read as a nested if) or an `else` block.
     */
    private function elseOf(): IfStmt|Block|null
    {
        if ($this->cursor->atName('elif')) {
            return $this->if();
        }

        return $this->cursor->advanceIfName('else') ? $this->block() : null;
    }

    private function for(bool $async): ForLoop
    {
        $this->cursor->advance(); // `for`
        $target = $this->expressions->targetList();
        $this->cursor->advanceIfName('in');
        $iterable = $this->expressions->expressionList();
        $body = $this->block();

        return new ForLoop($target, $iterable, $body, $this->cursor->advanceIfName('else') ? $this->block() : null, $async);
    }

    private function while(): WhileLoop
    {
        $this->cursor->advance(); // `while`
        $test = $this->expressions->expression();
        $body = $this->block();

        return new WhileLoop($test, $body, $this->cursor->advanceIfName('else') ? $this->block() : null);
    }

    private function try(): TryStmt
    {
        $this->cursor->advance(); // `try`
        $body = $this->block();
        $handlers = [];

        while ($this->cursor->atName('except')) {
            $handlers[] = $this->handler();
        }

        $else = $this->cursor->advanceIfName('else') ? $this->block() : null;
        $finally = $this->cursor->advanceIfName('finally') ? $this->block() : null;

        return new TryStmt($body, $handlers, $else, $finally);
    }

    private function handler(): ExceptHandler
    {
        $start = $this->cursor->offset();
        $this->cursor->advance(); // `except`
        $group = $this->cursor->advanceIfOp('*');
        $type = $this->cursor->atOp(':') ? null : $this->expressions->expression();
        $name = $this->cursor->advanceIfName('as') ? $this->cursor->advance()->value : null;

        return $this->located(new ExceptHandler($type, $name, $this->block(), $group), $start);
    }

    private function with(bool $async): With
    {
        $this->cursor->advance(); // `with`
        $parenthesised = $this->cursor->atOp('(') && $this->parenthesisesItems();

        if ($parenthesised) {
            $this->cursor->advance();
        }

        $contexts = [];
        $targets = [];

        do {
            if ($parenthesised && $this->cursor->atOp(')')) {
                break;
            }

            $contexts[] = $this->expressions->expression();
            $targets[] = $this->cursor->advanceIfName('as') ? $this->expressions->target() : null;
        } while ($this->cursor->advanceIfOp(','));

        if ($parenthesised) {
            $this->cursor->advanceIfOp(')');
        }

        return new With($contexts, $targets, $this->block(), $async);
    }

    /**
     * Does the `(` after `with` enclose the items themselves — `with (a as x, b as y):` — rather than
     * open the first context's own expression, like `with (path / "x").open() as fh:`?
     */
    private function parenthesisesItems(): bool
    {
        $mark = $this->cursor->mark();
        $this->cursor->skipGroup();
        $items = $this->cursor->atOp(':');
        $this->cursor->rewind($mark);

        return $items;
    }

    private function match(): MatchStmt
    {
        $this->cursor->advance(); // `match`
        $subject = $this->expressions->expressionList();
        $this->cursor->advanceIfOp(':');
        $this->endLine();
        $this->cursor->advance(); // INDENT
        $cases = [];

        while ($this->cursor->atName('case')) {
            $start = $this->cursor->offset();
            $this->cursor->advance();
            $pattern = $this->expressions->patternList();
            $guard = $this->cursor->advanceIfName('if') ? $this->expressions->expression() : null;
            $cases[] = $this->located(new MatchCase($pattern, $guard, $this->block()), $start);
        }

        if ($this->cursor->peek()->is(TokenKind::Dedent)) {
            $this->cursor->advance();
        }

        return new MatchStmt($subject, $cases);
    }

    /**
     * The block after a compound statement's `:` — an indented suite, or simple statements on the same line.
     */
    private function block(): Block
    {
        $this->cursor->advanceIfOp(':');
        $start = $this->cursor->offset();

        if (! $this->cursor->peek()->is(TokenKind::Newline)) {
            return $this->located(new Block($this->simpleLine()), $start);
        }

        $this->cursor->advance(); // NEWLINE

        if (! $this->cursor->peek()->is(TokenKind::Indent)) {
            return $this->located(new Block(), $start);
        }

        $this->cursor->advance(); // INDENT
        $body = $this->statementsUntil(TokenKind::Dedent);

        if ($this->cursor->peek()->is(TokenKind::Dedent)) {
            $this->cursor->advance();
        }

        return $this->located(new Block($body), $start);
    }

    /**
     * The simple statements of one line, joined by `;`, and the line's end.
     *
     * @return list<Node>
     */
    private function simpleLine(): array
    {
        $statements = [];

        do {
            if ($this->isAtLineEnd()) {
                break;
            }

            $start = $this->cursor->offset();
            $statements[] = $this->located($this->simple(), $start);
        } while ($this->cursor->advanceIfOp(';'));

        $this->endLine();

        return $statements;
    }

    private function simple(): Node
    {
        $token = $this->cursor->peek();

        return match (true) {
            $token->isName('return') => $this->return(),
            $token->isName('raise') => $this->raise(),
            $token->isName('import') => $this->import(),
            $token->isName('from') => $this->fromImport(),
            $token->isName('break'), $token->isName('continue') => new Jump($this->cursor->advance()->value),
            $token->isName('pass') => new Simple($this->cursor->advance()->value),
            $token->isName('del') => new Simple($this->cursor->advance()->value, [$this->expressions->targetList()]),
            $token->isName('assert') => $this->assert(),
            $token->isName('global'), $token->isName('nonlocal') => $this->scopeDeclaration(),
            $token->isName('type') && $this->cursor->at(1)->isName() && ($this->cursor->at(2)->isOp('=') || $this->cursor->at(2)->isOp('[')) => $this->typeAlias(),
            default => $this->expressionStatement(),
        };
    }

    private function return(): Return_
    {
        $this->cursor->advance(); // `return`

        return new Return_($this->expressions->isAtExpression() ? $this->expressions->expressionList() : null);
    }

    private function raise(): Raise
    {
        $this->cursor->advance(); // `raise`

        if (! $this->expressions->isAtExpression()) {
            return new Raise();
        }

        $exception = $this->expressions->expression();

        return new Raise($exception, $this->cursor->advanceIfName('from') ? $this->expressions->expression() : null);
    }

    private function import(): Import
    {
        $this->cursor->advance(); // `import`
        $names = [];

        do {
            $name = $this->dottedName();
            $names[$name] = $this->cursor->advanceIfName('as') ? $this->cursor->advance()->value : explode('.', $name)[0];
        } while ($this->cursor->advanceIfOp(','));

        return new Import($names);
    }

    private function fromImport(): Import
    {
        $this->cursor->advance(); // `from`
        $level = 0;

        while ($this->cursor->atOp('.') || $this->cursor->atOp('...')) {
            $level += strlen($this->cursor->advance()->value);
        }

        $module = $this->cursor->atName('import') ? '' : $this->dottedName();
        $this->cursor->advanceIfName('import');
        $parenthesised = $this->cursor->advanceIfOp('(');
        $names = [];

        do {
            if ($this->cursor->atOp(')')) {
                break;
            }

            $name = $this->cursor->advance()->value;
            $names[$name] = $this->cursor->advanceIfName('as') ? $this->cursor->advance()->value : $name;
        } while ($this->cursor->advanceIfOp(','));

        if ($parenthesised) {
            $this->cursor->advanceIfOp(')');
        }

        return new Import($names, $module, $level);
    }

    private function dottedName(): string
    {
        $name = $this->cursor->advance()->value;

        while ($this->cursor->atDottedName()) {
            $this->cursor->advance();
            $name .= '.' . $this->cursor->advance()->value;
        }

        return $name;
    }

    private function assert(): Simple
    {
        $keyword = $this->cursor->advance()->value;
        $holds = [$this->expressions->expression()];

        if ($this->cursor->advanceIfOp(',')) {
            $holds[] = $this->expressions->expression();
        }

        return new Simple($keyword, $holds);
    }

    private function scopeDeclaration(): Simple
    {
        $keyword = $this->cursor->advance()->value;

        return new Simple($keyword, [$this->expressions->expressionList()]);
    }

    private function typeAlias(): Simple
    {
        $keyword = $this->cursor->advance()->value;
        $name = $this->expressions->expression();
        $this->skipTypeParameters();
        $this->cursor->advanceIfOp('=');

        return new Simple($keyword, [$name, $this->expressions->expression()]);
    }

    /**
     * An expression standing alone, or the start of an assignment: `a = b = value`, an augmented
     * `total += x`, or an annotated `limit: int = 10`.
     */
    private function expressionStatement(): Node
    {
        $first = $this->valueList();
        $operator = $this->cursor->peek();

        if ($operator->kind === TokenKind::Op && in_array($operator->value, self::AUGMENTED, true)) {
            $this->cursor->advance();

            return new AugAssign($first, $operator->value, $this->valueList());
        }

        if ($this->cursor->advanceIfOp(':')) {
            $annotation = $this->expressions->expression();

            return new AnnAssign($first, $annotation, $this->cursor->advanceIfOp('=') ? $this->valueList() : null);
        }

        if (! $this->cursor->atOp('=')) {
            return new ExprStmt($first);
        }

        $targets = [$first];

        while ($this->cursor->advanceIfOp('=')) {
            $targets[] = $this->valueList();
        }

        $value = array_pop($targets);

        return new Assign($targets, $value);
    }

    /**
     * What stands on either side of an `=`: an expression list, or a `yield`.
     */
    private function valueList(): Expr
    {
        return $this->cursor->atName('yield') ? $this->expressions->expression() : $this->expressions->expressionList();
    }

    /**
     * Close a line: the NEWLINE that ends it, stepping over whatever the statement left unread.
     */
    private function endLine(): void
    {
        while (! $this->isAtLineEnd()) {
            $this->cursor->advance();
        }

        if ($this->cursor->peek()->is(TokenKind::Newline)) {
            $this->cursor->advance();
        }
    }

    private function isAtLineEnd(): bool
    {
        $token = $this->cursor->peek();

        return $token->is(TokenKind::Newline) || $token->is(TokenKind::EndMarker) || $token->is(TokenKind::Dedent) || $token->is(TokenKind::Indent);
    }

    /**
     * @template T of Node
     * @param  T  $node
     * @return T
     */
    private function located(Node $node, int $start): Node
    {
        return $node->locatedAt($start, max($start, $this->cursor->consumedEnd()));
    }
}
