<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts;

use JesseGall\CodeCommandments\Ts\Keyword;
use JesseGall\CodeCommandments\Ts\Lexeme;
use JesseGall\CodeCommandments\Ts\Token;
use JesseGall\CodeCommandments\Ts\Expr\Expr;
use JesseGall\CodeCommandments\Ts\Expr\ExprKind;
use JesseGall\CodeCommandments\Ts\Expr\Parser as ExprParser;
use JesseGall\CodeCommandments\Ts\Node\ArrayPattern;
use JesseGall\CodeCommandments\Ts\Node\ArrayType;
use JesseGall\CodeCommandments\Ts\Node\CallExpr;
use JesseGall\CodeCommandments\Ts\Node\CatchClause;
use JesseGall\CodeCommandments\Ts\Node\ClassDecl;
use JesseGall\CodeCommandments\Ts\Node\BlockStmt;
use JesseGall\CodeCommandments\Ts\Node\ExprStmt;
use JesseGall\CodeCommandments\Ts\Node\FieldDecl;
use JesseGall\CodeCommandments\Ts\Node\IfStmt;
use JesseGall\CodeCommandments\Ts\Node\LoopStmt;
use JesseGall\CodeCommandments\Ts\Node\MethodDecl;
use JesseGall\CodeCommandments\Ts\Node\ReturnStmt;
use JesseGall\CodeCommandments\Ts\Node\SwitchCase;
use JesseGall\CodeCommandments\Ts\Node\SwitchStmt;
use JesseGall\CodeCommandments\Ts\Node\ThrowStmt;
use JesseGall\CodeCommandments\Ts\Node\TryStmt;
use JesseGall\CodeCommandments\Ts\Node\CompositeType;
use JesseGall\CodeCommandments\Ts\Node\FunctionDecl;
use JesseGall\CodeCommandments\Ts\Node\FunctionType;
use JesseGall\CodeCommandments\Ts\Node\ImportDecl;
use JesseGall\CodeCommandments\Ts\Node\IndexedAccessType;
use JesseGall\CodeCommandments\Ts\Node\InterfaceDecl;
use JesseGall\CodeCommandments\Ts\Node\KeywordType;
use JesseGall\CodeCommandments\Ts\Node\LiteralType;
use JesseGall\CodeCommandments\Ts\Node\Member;
use JesseGall\CodeCommandments\Ts\Node\Method;
use JesseGall\CodeCommandments\Ts\Node\Module;
use JesseGall\CodeCommandments\Ts\Node\NamedType;
use JesseGall\CodeCommandments\Ts\Node\NamePattern;
use JesseGall\CodeCommandments\Ts\Node\Node;
use JesseGall\CodeCommandments\Ts\Node\ObjectPattern;
use JesseGall\CodeCommandments\Ts\Node\ObjectType;
use JesseGall\CodeCommandments\Ts\Node\Param;
use JesseGall\CodeCommandments\Ts\Node\ParenType;
use JesseGall\CodeCommandments\Ts\Node\Pattern;
use JesseGall\CodeCommandments\Ts\Node\Property;
use JesseGall\CodeCommandments\Ts\Node\TupleType;
use JesseGall\CodeCommandments\Ts\Node\TypeAliasDecl;
use JesseGall\CodeCommandments\Ts\Node\TypeNode;
use JesseGall\CodeCommandments\Ts\Node\TypeofType;
use JesseGall\CodeCommandments\Ts\Node\VariableDecl;
use JesseGall\CodeCommandments\Ts\Node\VerbatimType;

/**
 * Recursive-descent parser for `<script setup>` — tokens ({@see Lexer}) → {@see Module} tree.
 * Models imports, declarations, calls, and full TYPE grammar (precedence-respecting with function
 * type arrows). "Can't fail": unmodeled constructs preserved verbatim rather than mis-parsed. Total.
 */
final class Parser
{
    private const array KEYWORD_TYPES = [
        'string', 'number', 'boolean', 'void', 'unknown', 'never', 'any', 'null', 'undefined',
        'object', 'symbol', 'bigint', 'this',
    ];

    /**
     * Type-level leads the grammar doesn't model — bail to verbatim so the whole region is kept.
     */
    private const array VERBATIM_LEADS = ['keyof', 'readonly', 'infer', 'unique', 'new', 'abstract', 'asserts'];

    /**
     * The deepest type nesting we recurse into before preserving the rest verbatim. Real component
     * types nest a handful deep; a checker can emit far deeper (or a malformed region could recurse
     * unboundedly), and the parser's contract is TOTAL — so past this we degrade to {@see VerbatimType}
     * rather than blow the stack.
     */
    private const int MAX_TYPE_DEPTH = 256;

    /**
     * Type-operator keywords that EXPECT a following type — a `{` after one still opens the type.
     */
    private const array TYPE_OPERATORS = ['keyof', 'typeof', 'readonly', 'infer', 'in', 'extends', 'as', 'is', 'new', 'unique', 'abstract', 'asserts'];

    /**
     * @var list<Lexeme>
     */
    private array $lexemes;

    private int $pos = 0;

    private int $typeDepth = 0;

    /**
     * $base is the offset IN THE FILE that $source begins at — 0 for a whole module, and the body's
     * own start when a nested parser reads a function body, so every node it stamps reports a
     * position in the file rather than in the fragment.
     */
    private function __construct(private readonly string $source, private readonly int $base = 0)
    {
        $this->lexemes = new Lexer()->tokenize($source);
    }

    /**
     * The absolute offset the token under the cursor begins at.
     */
    private function offset(): int
    {
        return $this->base + $this->peek()->start;
    }

    /**
     * The absolute offset just past the last CONSUMED token.
     */
    private function consumedEnd(): int
    {
        return $this->base + ($this->lexemes[$this->pos - 1]->end ?? 0);
    }

    /**
     * Stamp a node with the span it was read from — the one place a statement rule records its
     * position.
     *
     * @template T of Node
     * @param  T  $node
     * @return T
     */
    private function located(Node $node, int $start): Node
    {
        return $node->locatedAt($start, $this->consumedEnd());
    }

    /**
     * $baseOffset is where $source begins in the file it came from — 0 for a standalone `.ts`, and
     * the block's own start for a component's `<script>`, so a node reports a line in the SFC.
     */
    public static function module(string $source, int $baseOffset = 0): Module
    {
        return new self($source, $baseOffset)->parseModule();
    }

    /**
     * Parse a single type — for testing the type grammar and for re-parsing a stored annotation.
     */
    public static function type(string $source): TypeNode
    {
        return new self($source)->parseType();
    }

    private function parseModule(): Module
    {
        $imports = [];
        $body = [];

        while (! $this->eof()) {
            $before = $this->pos;
            $node = $this->parseStatement();

            if ($node instanceof ImportDecl) {
                $imports[] = $node;
            } elseif ($node !== null) {
                $body[] = $node;
            }

            if ($this->pos === $before) {
                $this->advance(); // the total-parser guarantee: a statement always makes progress
            }
        }

        return new Module($imports, $body);
    }

    /**
     * One statement — or null for one this grammar does not model. This is where the class's TOTAL
     * contract is kept: a statement that runs off the end of the source (`function f(` in a
     * truncated file) leaves the cursor mid-construct, so the failure is caught HERE and recovered
     * by skipping to the statement's end, exactly as an unmodelled construct already is. Without
     * it an `Unparsed` escapes `module()`, which promises it never fails.
     */
    private function parseStatement(): ?Node
    {
        $start = $this->offset();

        try {
            $statement = $this->parseModelledStatement();
        } catch (Unparsed) {
            $this->skipStatement();

            return null;
        }

        return $statement === null ? null : $this->located($statement, $start);
    }

    /**
     * THE statement grammar — one arm per kind, and the same one whether the statement stands at
     * module level or inside a body. A module legitimately holds control flow (`if (import.meta.hot)
     * { … }` is ordinary), and a body legitimately holds a declaration, so splitting this in two only
     * ever meant each half was blind to what the other could read.
     */
    private function parseModelledStatement(): ?Node
    {
        return match (true) {
            $this->atImportDeclaration() => $this->parseImport(),
            $this->atId(Keyword::EXPORT) => $this->parseExported(),
            $this->atId(Keyword::INTERFACE) => $this->parseInterface(),
            $this->atId(Keyword::TYPE) && $this->at(1)->isIdentifier() => $this->parseTypeAlias(),
            $this->atPunct(Token::SEMICOLON) => $this->skipEmptyStatement(),
            $this->atPunct(Token::BRACE_OPEN) => $this->parseBlock(),
            $this->atId(Keyword::IF) => $this->parseIf(),
            $this->atId(Keyword::SWITCH) => $this->parseSwitch(),
            $this->atId(Keyword::TRY) => $this->parseTry(),
            $this->atId(Keyword::RETURN) => $this->parseReturn(),
            $this->atId(Keyword::THROW) => $this->parseThrow(),
            $this->atId(Keyword::FOR), $this->atId(Keyword::WHILE), $this->atId(Keyword::DO) => $this->parseLoop(),
            $this->atId(Keyword::BREAK), $this->atId(Keyword::CONTINUE) => $this->skipEmptyStatement(),
            $this->atId(Keyword::CONST), $this->atId(Keyword::LET), $this->atId(Keyword::VAR) => $this->parseVariable(),
            $this->atId(Keyword::CLASS_), $this->atId('abstract') && $this->at(1)->isIdentifier(Keyword::CLASS_) => $this->parseClass(),
            $this->atId(Keyword::FUNCTION), $this->atId(Keyword::ASYNC) && $this->at(1)->isIdentifier(Keyword::FUNCTION) => $this->parseFunction(),
            $this->atCall() => $this->parseCallStatement(),
            default => $this->parseExpressionStatement(),
        };
    }

    /**
     * An `import` DECLARATION — not `import.meta` or a dynamic `import(…)`, which are expressions.
     */
    private function atImportDeclaration(): bool
    {
        return $this->atId(Keyword::IMPORT)
            && ! $this->at(1)->isPunct(Token::DOT)
            && ! $this->at(1)->isPunct(Token::PAREN_OPEN);
    }

    /**
     * `export` fronts a declaration — read the declaration it introduces.
     */
    private function parseExported(): ?Node
    {
        $this->advance();

        return $this->parseStatement();
    }

    /**
     * A bare call standing as a statement — a macro (`defineProps<…>()`) or a side effect. It stays a
     * {@see CallExpr} so {@see Module::call} still finds a macro however it was written, and carries
     * its parsed expression so a rule about calls sees it like any other.
     */
    private function parseCallStatement(): ?Node
    {
        $start = $this->peek()->start;
        $call = $this->tryCall();

        if ($call === null) {
            return $this->parseExpressionStatement();
        }

        return new CallExpr($call->callee, $call->typeArguments, $call->arguments, $this->expressionBetween($start, $this->consumeToStatementEnd()));
    }

    // ---- declarations ---------------------------------------------------------

    private function parseImport(): ImportDecl
    {
        $start = $this->peek()->start;
        $bindings = [];
        $source = null;
        $typeOnly = false;
        $this->advance(); // `import`

        if ($this->atId(Keyword::TYPE)) {
            $typeOnly = true;
            $this->advance();
        }

        // `import X = App.Http.View.Page;` (TS import-equals alias)
        if ($this->peek()->isIdentifier() && $this->at(1)->isPunct(Token::ASSIGN)) {
            $local = $this->advance()->value;
            $this->advance(); // `=`
            $bindings[$local] = $this->qualifiedName();
        } else {
            $bindings = $this->parseImportBindings();

            if ($this->atId(Keyword::FROM)) {
                $this->advance();
                $source = $this->peek()->is(Token::STRING) ? substr($this->advance()->value, 1, -1) : null;
            } elseif ($this->peek()->is(Token::STRING)) {
                $source = substr($this->advance()->value, 1, -1); // side-effect `import '...'`
            }
        }

        $end = $this->consumeToStatementEnd();

        return new ImportDecl($bindings, $source, $typeOnly, trim(substr($this->source, $start, $end - $start)));
    }

    /**
     * @return array<string, string>  local => imported member (`default`, `*`, or the name)
     */
    private function parseImportBindings(): array
    {
        $bindings = [];

        if ($this->peek()->isIdentifier() && ! $this->atPunct(Token::BRACE_OPEN) && ! $this->atPunct(Token::STAR)) {
            $bindings[$this->advance()->value] = 'default';

            if ($this->atPunct(Token::COMMA)) {
                $this->advance();
            }
        }

        if ($this->atPunct(Token::STAR)) {
            $this->advance();
            $this->advanceIfId(Keyword::AS);
            $bindings[$this->advance()->value] = '*';
        }

        if ($this->atPunct(Token::BRACE_OPEN)) {
            $this->advance();

            while ($this->insideGroupClosedBy(Token::BRACE_CLOSE)) {
                $this->advanceIfId(Keyword::TYPE);
                $imported = $this->advance()->value;
                $local = $this->advanceIfId(Keyword::AS) ? $this->advance()->value : $imported;
                $bindings[$local] = $imported;

                if ($this->atPunct(Token::COMMA)) {
                    $this->advance();
                }
            }

            $this->advanceIfPunct(Token::BRACE_CLOSE);
        }

        return $bindings;
    }

    private function parseInterface(): InterfaceDecl
    {
        $this->advance(); // `interface`
        $name = $this->advance()->value;
        $header = $this->consumeUntilPunct(Token::BRACE_OPEN); // type params + extends clause, kept verbatim

        return new InterfaceDecl($name, $this->parseTypeMembers(strict: false), $header);
    }

    private function parseTypeAlias(): TypeAliasDecl
    {
        $this->advance(); // `type`
        $name = $this->advance()->value;
        $header = $this->consumeUntilPunct(Token::ASSIGN); // type params, kept verbatim
        $this->advanceIfPunct(Token::ASSIGN);
        $type = $this->parseType();
        $this->consumeToStatementEnd();

        return new TypeAliasDecl($name, $type, $header);
    }

    private function parseVariable(): VariableDecl
    {
        $keyword = $this->advance()->value;
        $pattern = $this->parsePattern();
        $type = null;
        $initRaw = null;
        $initCall = null;
        $initParams = null;
        $initReturnType = null;

        if ($this->atPunct(Token::COLON)) {
            $this->advance();
            $type = $this->parseType();
        }

        if ($this->atPunct(Token::ASSIGN)) {
            $this->advance();
            $this->advanceIfId(Keyword::AWAIT); // `= await useX()` — trace through to the call
            $initStart = $this->peek()->start;

            if ($this->atCall()) {
                $initCall = $this->tryCall();
            } elseif ($this->atPunct(Token::PAREN_OPEN)) {
                [$initParams, $initReturnType] = $this->tryArrowSignature();
            }

            $initEnd = $this->consumeToStatementEnd();
            // `consumeToStatementEnd` swallows the terminating `;`; the initializer is the expression
            // WITHOUT it (VariableDecl::render re-appends one), so strip a trailing statement `;`.
            $initRaw = rtrim(trim(substr($this->source, $initStart, $initEnd - $initStart)), ';');
            $initializer = $this->expressionBetween($initStart, $initEnd);
        } else {
            $this->consumeToStatementEnd();
        }

        return new VariableDecl($keyword, $pattern, $type, $initRaw, $initCall, $initParams, $initReturnType, $initializer ?? null);
    }

    private function parseFunction(): FunctionDecl
    {
        $this->advanceIfId(Keyword::ASYNC);
        $this->advance(); // `function`
        $this->advanceIfPunct(Token::STAR); // generator
        $name = $this->advance()->value;
        $this->consumeUntilPunct(Token::PAREN_OPEN); // type params
        $params = $this->parseParams();
        $returnType = null;

        if ($this->atPunct(Token::COLON)) {
            $this->advance();
            $returnType = $this->parseType();
        }

        $bodyStart = $this->peek()->start + 1; // just past the opening `{`
        $returnObject = $this->skipBodyCapturingReturn();
        $bodyEnd = $this->lexemes[$this->pos - 1]->start ?? $bodyStart; // the closing `}`
        $bodySource = $bodyEnd > $bodyStart ? substr($this->source, $bodyStart, $bodyEnd - $bodyStart) : '';

        return new FunctionDecl($name, $params, $returnType, $returnObject, $bodySource, $this->bodyOf($bodySource, $bodyStart));
    }

    /**
     * A function body's statements, parsed from the source the skip already delimited. Re-reading the
     * region with its own cursor keeps the {@see skipBodyCapturingReturn} contract (the composable
     * return-shape every existing reader depends on) exactly as it was, while the statements it
     * skipped past become a tree — and $offset makes every node in it report a position in the FILE.
     */
    private function bodyOf(string $source, int $offset): BlockStmt
    {
        if (trim($source) === '') {
            return new BlockStmt();
        }

        $body = new self($source, $offset)->parseStatementsToEnd();

        return new BlockStmt($body)->locatedAt($offset, $offset + strlen($source));
    }

    /**
     * Every statement in this parser's whole source — the body reader's entry point, and total in the
     * same way {@see parseModule} is: a statement that makes no progress is stepped over rather than
     * allowed to spin.
     *
     * @return list<Node>
     */
    private function parseStatementsToEnd(): array
    {
        $body = [];

        while (! $this->eof()) {
            $before = $this->pos;
            $statement = $this->parseStatement();

            if ($statement !== null) {
                $body[] = $statement;
            }

            if ($this->pos === $before) {
                $this->advance();
            }
        }

        return $body;
    }

    /**
     * Skip a function body `{ … }`, but capture a top-level `return { … }` object's shape (field =>
     * the local it returns) — so a composable with an INFERRED return type can still be typed
     * field-by-field from its own declarations. Null when the body returns no object literal.
     *
     * @return ?array<string, ?string>
     */
    private function skipBodyCapturingReturn(): ?array
    {
        if (! $this->atPunct(Token::BRACE_OPEN)) {
            return null;
        }

        $this->advance(); // `{`
        $depth = 1;
        $returnObject = null;

        while (! $this->eof() && $depth > 0) {
            if ($depth === 1 && $this->atId(Keyword::RETURN) && $this->at(1)->isPunct(Token::BRACE_OPEN)) {
                $this->advance(); // `return`
                $returnObject = $this->parseObjectShape();

                continue;
            }

            $token = $this->advance();

            if ($token->isPunct(Token::BRACE_OPEN)) {
                $depth++;
            } elseif ($token->isPunct(Token::BRACE_CLOSE)) {
                $depth--;
            }
        }

        return $returnObject;
    }

    /**
     * A `{ a, b: c, ... }` object literal's shape — each field mapped to the LOCAL name it takes its
     * value from (shorthand `a` → `a`; alias `b: c` → `c`; a complex value → null, unresolvable). A
     * spread or method shorthand is skipped.
     *
     * @return array<string, ?string>
     */
    private function parseObjectShape(): array
    {
        $this->advance(); // `{`
        $shape = [];

        while ($this->insideGroupClosedBy(Token::BRACE_CLOSE)) {
            $before = $this->pos;

            if ($this->atThreeDots()) {
                $this->advanceIfThreeDots();
                $this->consumeExpression([',', '}']);
            } elseif ($this->peek()->isIdentifier() || $this->peek()->is(Token::STRING)) {
                $key = $this->advance()->value;

                if (! $this->advanceIfPunct(Token::COLON)) {
                    $shape[$key] = $key; // shorthand `{ a }`
                } elseif ($this->peek()->isIdentifier() && ($this->at(1)->isPunct(Token::COMMA) || $this->at(1)->isPunct(Token::BRACE_CLOSE))) {
                    $shape[$key] = $this->advance()->value; // alias `{ a: b }`
                } else {
                    $shape[$key] = null; // a computed value — only a type checker could resolve it
                    $this->consumeExpression([',', '}']);
                }
            } else {
                $this->consumeExpression([',', '}']); // method shorthand / computed key
            }

            $this->advanceIfPunct(Token::COMMA);

            if ($this->pos === $before) {
                $this->advance();
            }
        }

        $this->advanceIfPunct(Token::BRACE_CLOSE);

        return $shape;
    }

    /**
     * If the initializer is an arrow function, its `(params)(: R)? =>` signature — the params and
     * the explicitly-annotated return (null when unannotated). Restores position and yields
     * `[null, null]` when the initializer isn't an arrow (a plain value/object/call).
     *
     * @return array{0: ?list<Param>, 1: ?TypeNode}
     */
    private function tryArrowSignature(): array
    {
        return $this->speculate(function (): array {
            $params = $this->parseParams();
            $returnType = $this->advanceIfPunct(Token::COLON) ? $this->parseType() : null;

            if (! $this->atPunct(Token::ASSIGN) || ! $this->at(1)->isPunct(Token::ANGLE_CLOSE)) {
                throw new Unparsed();
            }

            return [$params, $returnType];
        }) ?? [null, null];
    }

    private function parsePattern(): Pattern
    {
        if ($this->atPunct(Token::BRACE_OPEN)) {
            return $this->parseObjectPattern();
        }

        if ($this->atPunct(Token::BRACKET_OPEN)) {
            return $this->parseArrayPattern();
        }

        return new NamePattern($this->advance()->value);
    }

    private function parseObjectPattern(): ObjectPattern
    {
        $this->advance(); // `{`
        $entries = [];
        $rest = null;

        while ($this->insideGroupClosedBy(Token::BRACE_CLOSE)) {
            if ($this->advanceIfThreeDots()) {
                $rest = $this->advance()->value;
            } else {
                $key = $this->advance()->value;
                $local = $this->advanceIfPunct(Token::COLON) ? $this->advance()->value : $key;
                $entries[$local] = $key;
                $this->skipDefaultValue();
            }

            if ($this->atPunct(Token::COMMA)) {
                $this->advance();
            }
        }

        $this->advanceIfPunct(Token::BRACE_CLOSE);

        return new ObjectPattern($entries, $rest);
    }

    private function parseArrayPattern(): ArrayPattern
    {
        $this->advance(); // `[`
        $elements = [];

        while ($this->insideGroupClosedBy(Token::BRACKET_CLOSE)) {
            if ($this->atPunct(Token::COMMA)) {
                $elements[] = null; // a hole
                $this->advance();

                continue;
            }

            $this->advanceIfThreeDots();
            $elements[] = $this->advance()->value;
            $this->skipDefaultValue();

            if ($this->atPunct(Token::COMMA)) {
                $this->advance();
            }
        }

        $this->advanceIfPunct(Token::BRACKET_CLOSE);

        return new ArrayPattern($elements);
    }

    // ---- type grammar ---------------------------------------------------------

    private function parseType(): TypeNode
    {
        $this->typeDepth++;

        try {
            $type = $this->speculate(function (): TypeNode {
                if ($this->typeDepth > self::MAX_TYPE_DEPTH) { // pathologically nested — keep it verbatim
                    throw new Unparsed();
                }

                $type = $this->parseUnion();

                if ($this->atId(Keyword::EXTENDS)) { // a conditional type — not modelled; keep whole region
                    throw new Unparsed();
                }

                return $type;
            });

            if ($type !== null) {
                return $type;
            }

            $start = $this->pos; // speculate rewound us to where the type began
            $verbatim = $this->captureTypeVerbatim();

            // Guarantee progress: if the verbatim reader consumed nothing (a stray terminator that
            // begins no type — e.g. a `...` from a truncated checker type), swallow one token so a
            // caller can never re-enter parseType at the same spot and spin. Total, by construction.
            if ($this->pos === $start && ! $this->eof()) {
                $verbatim = $this->advance()->value;
            }

            return new VerbatimType($verbatim);
        } finally {
            $this->typeDepth--;
        }
    }

    private function parseUnion(): TypeNode
    {
        $this->advanceIfPunct(Token::PIPE); // a leading `|` is allowed
        $members = [$this->parseIntersection()];

        while ($this->atPunct(Token::PIPE)) {
            $this->advance();
            $members[] = $this->parseIntersection();
        }

        return count($members) === 1 ? $members[0] : new CompositeType('|', $members);
    }

    private function parseIntersection(): TypeNode
    {
        $this->advanceIfPunct(Token::AMPERSAND);
        $members = [$this->parsePostfix()];

        while ($this->atPunct(Token::AMPERSAND)) {
            $this->advance();
            $members[] = $this->parsePostfix();
        }

        return count($members) === 1 ? $members[0] : new CompositeType('&', $members);
    }

    private function parsePostfix(): TypeNode
    {
        $type = $this->parsePrimary();

        while ($this->atPunct(Token::BRACKET_OPEN)) {
            $this->advance();

            if ($this->atPunct(Token::BRACKET_CLOSE)) {
                $this->advance();
                $type = new ArrayType($type);
            } else {
                $index = $this->parseType();
                $this->expectPunct(Token::BRACKET_CLOSE);
                $type = new IndexedAccessType($type, $index);
            }
        }

        return $type;
    }

    private function parsePrimary(): TypeNode
    {
        $token = $this->peek();

        if ($token->isNone()) {
            throw new Unparsed(); // a primary type was expected and the source ended
        }

        if ($token->isPunct(Token::PAREN_OPEN)) {
            return $this->parseParenOrFunction();
        }

        if ($token->isPunct(Token::BRACE_OPEN)) {
            return new ObjectType($this->parseTypeMembers(strict: true));
        }

        if ($token->isPunct(Token::BRACKET_OPEN)) {
            return $this->parseTuple();
        }

        if ($token->isPunct(Token::MINUS) && $this->at(1)->is(Token::NUMBER)) {
            $this->advance();

            return new LiteralType('-' . $this->advance()->value);
        }

        if ($token->is(Token::STRING) || $token->is(Token::NUMBER)) {
            return new LiteralType($this->advance()->value);
        }

        if ($token->isIdentifier(Keyword::TYPEOF)) {
            $this->advance();

            return new TypeofType($this->qualifiedName());
        }

        if ($token->isIdentifier()) {
            if (in_array($token->value, self::VERBATIM_LEADS, true)) {
                throw new Unparsed();
            }

            return $this->parseNamedOrKeyword();
        }

        throw new Unparsed();
    }

    private function parseNamedOrKeyword(): TypeNode
    {
        $name = $this->qualifiedName();

        if ($this->atPunct(Token::ANGLE_OPEN)) {
            return new NamedType($name, $this->parseTypeArguments());
        }

        if ($name === 'true' || $name === 'false') {
            return new LiteralType($name);
        }

        if (in_array($name, self::KEYWORD_TYPES, true)) {
            return new KeywordType($name);
        }

        return new NamedType($name);
    }

    private function parseParenOrFunction(): TypeNode
    {
        $function = $this->speculate(function (): FunctionType {
            $params = $this->parseParams();

            if (! $this->atPunct(Token::ASSIGN) || ! $this->at(1)->isPunct(Token::ANGLE_CLOSE)) {
                throw new Unparsed();
            }

            $this->advance();
            $this->advance(); // `=>`

            return new FunctionType($params, $this->parseType());
        });

        if ($function !== null) {
            return $function;
        }

        $this->expectPunct(Token::PAREN_OPEN);
        $inner = $this->parseType();
        $this->expectPunct(Token::PAREN_CLOSE);

        return new ParenType($inner);
    }

    private function parseTuple(): TupleType
    {
        $this->advance(); // `[`
        $elements = [];

        while ($this->insideGroupClosedBy(Token::BRACKET_CLOSE)) {
            $elements[] = $this->parseType();

            if ($this->atPunct(Token::COMMA)) {
                $this->advance();
            }
        }

        $this->expectPunct(Token::BRACKET_CLOSE);

        return new TupleType($elements);
    }

    /**
     * @return list<TypeNode>
     */
    private function parseTypeArguments(): array
    {
        $this->advance(); // `<`
        $arguments = [];

        while (! $this->atPunct(Token::ANGLE_CLOSE) && ! $this->eof()) {
            $arguments[] = $this->parseType();

            if ($this->atPunct(Token::COMMA)) {
                $this->advance();
            }
        }

        $this->expectPunct(Token::ANGLE_CLOSE);

        return $arguments;
    }

    /**
     * The members of an object type or interface. When $strict (an INLINE object type, which must
     * re-render exactly), a member the grammar can't model — an index signature `[k: T]: V`, a
     * computed key, a getter — bails to {@see Unparsed} so the WHOLE object is kept verbatim and
     * nothing is lost. When not strict (an interface, read for its named fields), such a member is
     * skipped.
     *
     * @return list<Member>
     */
    private function parseTypeMembers(bool $strict): array
    {
        $this->expectPunct(Token::BRACE_OPEN);
        $members = [];

        while ($this->insideGroupClosedBy(Token::BRACE_CLOSE)) {
            $named = $this->peek()->isIdentifier() || $this->peek()->is(Token::STRING) || $this->atId('readonly');

            if (! $named) {
                if ($strict) {
                    throw new Unparsed();
                }

                $this->consumeMemberVerbatim();
                $this->advanceIfPunct(Token::SEMICOLON);
                $this->advanceIfPunct(Token::COMMA);

                continue;
            }

            $members[] = $this->parseTypeMember($strict);
            $this->advanceIfPunct(Token::SEMICOLON);
            $this->advanceIfPunct(Token::COMMA);
        }

        $this->expectPunct(Token::BRACE_CLOSE);

        return $members;
    }

    private function parseTypeMember(bool $strict): Member
    {
        if ($this->atReadonlyModifier()) {
            $this->advance();
        }

        $name = $this->advance()->value;
        $optional = $this->advanceIfPunct(Token::QUESTION);

        if ($this->atPunct(Token::PAREN_OPEN)) {
            $params = $this->parseParams();
            $returnType = $this->advanceIfPunct(Token::COLON) ? $this->parseType() : new KeywordType('void');

            return new Method($name, $params, $returnType, $optional);
        }

        if ($this->advanceIfPunct(Token::COLON)) {
            return new Property($name, $this->parseType(), $optional);
        }

        throw new Unparsed(); // a member shape we don't model — keep the whole object verbatim
    }

    /**
     * @return list<Param>
     */
    private function parseParams(): array
    {
        $this->expectPunct(Token::PAREN_OPEN);
        $params = [];

        while ($this->insideGroupClosedBy(Token::PAREN_CLOSE)) {
            $start = $this->offset();
            $this->memberModifiers(); // a constructor PARAMETER PROPERTY — `private readonly x: T`
            $rest = $this->advanceIfThreeDots();

            if (! $this->peek()->isIdentifier() && ! $this->atPunct(Token::BRACE_OPEN) && ! $this->atPunct(Token::BRACKET_OPEN)) {
                throw new Unparsed(); // a param we can't name (destructured param type) — bail
            }

            $name = $this->atPunct(Token::BRACE_OPEN) || $this->atPunct(Token::BRACKET_OPEN) ? $this->parsePattern()->render() : $this->advance()->value;
            $optional = $this->advanceIfPunct(Token::QUESTION);
            $type = $this->advanceIfPunct(Token::COLON) ? $this->parseType() : null;
            $this->skipDefaultValue();
            $params[] = $this->located(new Param($name, $type, $optional, $rest), $start);

            if ($this->atPunct(Token::COMMA)) {
                $this->advance();
            }
        }

        $this->expectPunct(Token::PAREN_CLOSE);

        return $params;
    }

    // ---- statements -----------------------------------------------------------

    private function skipEmptyStatement(): ?Node
    {
        $this->consumeToStatementEnd();

        return null;
    }

    private function parseBlock(): BlockStmt
    {
        $start = $this->offset();
        $this->expectPunct(Token::BRACE_OPEN);
        $body = $this->statementsUntil(static fn (self $parser): bool => $parser->atPunct(Token::BRACE_CLOSE));
        $this->advanceIfPunct(Token::BRACE_CLOSE);

        return $this->located(new BlockStmt($body), $start);
    }

    /**
     * Statements up to $ends (or EOF).
     *
     * @param  callable(self): bool  $ends
     * @return list<Node>
     */
    private function statementsUntil(callable $ends): array
    {
        return $this->collectUntil($ends, fn () => $this->parseStatement());
    }

    /**
     * What $parse yields, repeatedly, up to $ends or EOF — with the guarantee that a round reading
     * NOTHING still steps the cursor. The one loop every bracketed run shares (a block's statements,
     * a switch's cases, a class's members), so none of them can spin on a construct it cannot read.
     *
     * @param  callable(self): bool  $ends
     * @param  callable(): ?Node  $parse
     * @return list<Node>
     */
    private function collectUntil(callable $ends, callable $parse): array
    {
        $collected = [];

        while (! $this->eof() && ! $ends($this)) {
            $before = $this->pos;
            $node = $parse();

            if ($node !== null) {
                $collected[] = $node;
            }

            if ($this->pos === $before) {
                $this->advance();
            }
        }

        return $collected;
    }

    private function parseIf(): IfStmt
    {
        $this->advance(); // `if`
        $test = $this->parenthesisedExpression();
        $then = $this->statementBody();
        $otherwise = $this->advanceIfId(Keyword::ELSE) ? $this->statementBody() : null;

        return new IfStmt($test, $then, $otherwise);
    }

    private function parseSwitch(): SwitchStmt
    {
        $this->advance(); // `switch`
        $subject = $this->parenthesisedExpression();
        $this->expectPunct(Token::BRACE_OPEN);
        $cases = $this->collectUntil(
            static fn (self $parser): bool => $parser->atPunct(Token::BRACE_CLOSE),
            fn () => $this->parseSwitchCase(),
        );
        $this->advanceIfPunct(Token::BRACE_CLOSE);

        return new SwitchStmt($subject, $cases);
    }

    /**
     * One `case v:` / `default:` arm and the statements under it — which run until the NEXT arm or
     * the closing brace, because that is the extent a fall-through arm really covers.
     */
    private function parseSwitchCase(): ?SwitchCase
    {
        $start = $this->offset();
        $test = null;

        if ($this->advanceIfId(Keyword::CASE)) {
            $test = $this->expressionUntil([Token::COLON]);
        } elseif (! $this->advanceIfId(Keyword::DEFAULT)) {
            return null;
        }

        $this->advanceIfPunct(Token::COLON);

        $body = $this->statementsUntil(static fn (self $parser): bool => $parser->atPunct(Token::BRACE_CLOSE)
            || $parser->atId(Keyword::CASE)
            || $parser->atId(Keyword::DEFAULT));

        return $this->located(new SwitchCase($test, $body), $start);
    }

    /**
     * A `for`/`for…of`/`for…in`/`while`/`do…while`. The head's expressions are kept as a list, so a
     * three-part `for` and a one-part `while` are read the same way.
     */
    private function parseLoop(): LoopStmt
    {
        $keyword = $this->advance()->value;

        if ($keyword === Keyword::DO) {
            $body = $this->statementBody();
            $this->advanceIfId(Keyword::WHILE);
            $head = $this->atPunct(Token::PAREN_OPEN) ? [$this->parenthesisedExpression()] : [];
            $this->consumeToStatementEnd();

            return new LoopStmt($keyword, $head, $body);
        }

        return new LoopStmt($keyword, $this->loopHead(), $this->statementBody());
    }

    /**
     * @return list<Expr>
     */
    private function loopHead(): array
    {
        $this->expectPunct(Token::PAREN_OPEN);
        $parts = [];

        while (! $this->eof() && ! $this->atPunct(Token::PAREN_CLOSE)) {
            $parts[] = $this->expressionUntil([Token::SEMICOLON, Token::PAREN_CLOSE]);

            if (! $this->advanceIfPunct(Token::SEMICOLON)) {
                break;
            }
        }

        $this->advanceIfPunct(Token::PAREN_CLOSE);

        return $parts;
    }

    private function parseReturn(): ReturnStmt
    {
        $this->advance(); // `return`

        if ($this->atPunct(Token::SEMICOLON) || $this->atPunct(Token::BRACE_CLOSE) || $this->eof()) {
            $this->advanceIfPunct(Token::SEMICOLON);

            return new ReturnStmt();
        }

        return new ReturnStmt($this->expressionToStatementEnd());
    }

    private function parseThrow(): ThrowStmt
    {
        $this->advance(); // `throw`

        return new ThrowStmt($this->expressionToStatementEnd());
    }

    private function parseTry(): TryStmt
    {
        $this->advance(); // `try`
        $body = $this->parseBlock();
        $catch = $this->atId(Keyword::CATCH) ? $this->parseCatch() : null;
        $finally = $this->advanceIfId(Keyword::FINALLY) ? $this->parseBlock() : null;

        return new TryStmt($body, $catch, $finally);
    }

    private function parseCatch(): CatchClause
    {
        $start = $this->offset();
        $this->advance(); // `catch`
        $parameter = null;

        if ($this->advanceIfPunct(Token::PAREN_OPEN)) {
            $parameter = $this->peek()->isIdentifier() ? $this->advance()->value : null;
            $this->consumeUntilPunct(Token::PAREN_CLOSE); // an `: unknown` annotation
            $this->advanceIfPunct(Token::PAREN_CLOSE);
        }

        return $this->located(new CatchClause($parameter, $this->parseBlock()), $start);
    }

    /**
     * A branch or loop's body — a braced block, or the single statement a brace-less one carries.
     */
    private function statementBody(): Node
    {
        if ($this->atPunct(Token::BRACE_OPEN)) {
            return $this->parseBlock();
        }

        return $this->parseStatement() ?? new BlockStmt();
    }

    private function parseExpressionStatement(): ?Node
    {
        $expression = $this->expressionToStatementEnd();

        return $expression->is(ExprKind::Unknown) ? null : new ExprStmt($expression);
    }

    // ---- classes --------------------------------------------------------------

    private function parseClass(): ClassDecl
    {
        $abstract = $this->advanceIfId('abstract');
        $this->advance(); // `class`
        $name = $this->peek()->isIdentifier() ? $this->advance()->value : '';
        $header = $this->consumeUntilPunct(Token::BRACE_OPEN); // type params + heritage, kept verbatim
        $this->expectPunct(Token::BRACE_OPEN);
        $members = $this->collectUntil(
            static fn (self $parser): bool => $parser->atPunct(Token::BRACE_CLOSE),
            fn () => $this->parseClassMember(),
        );
        $this->advanceIfPunct(Token::BRACE_CLOSE);

        return new ClassDecl($name, $members, $header, $abstract);
    }

    /**
     * One member — a {@see MethodDecl} when a parameter list follows the name, a {@see FieldDecl}
     * otherwise. A member this grammar cannot name is consumed rather than mis-read.
     */
    private function parseClassMember(): MethodDecl|FieldDecl|null
    {
        $start = $this->offset();

        if ($this->advanceIfPunct(Token::SEMICOLON)) {
            return null;
        }

        $modifiers = $this->memberModifiers();
        $accessor = $this->memberAccessor();
        $this->advanceIfPunct(Token::STAR); // generator
        $this->advanceIfPunct(Token::HASH); // `#private` name

        if (! $this->peek()->isIdentifier() && ! $this->peek()->is(Token::STRING)) {
            $this->consumeMemberVerbatim();
            $this->advanceIfPunct(Token::SEMICOLON);

            return null;
        }

        $name = $this->advance()->value;

        return $this->atPunct(Token::PAREN_OPEN) || $this->atPunct(Token::ANGLE_OPEN)
            ? $this->located(new MethodDecl($name, ...$this->methodTail(), modifiers: $modifiers, accessor: $accessor), $start)
            : $this->located($this->fieldTail($name, $modifiers), $start);
    }

    /**
     * A method's parameters, return type and body — everything after its name.
     *
     * @return array{params: list<Param>, returnType: ?TypeNode, body: ?BlockStmt}
     */
    private function methodTail(): array
    {
        $this->consumeUntilPunct(Token::PAREN_OPEN); // type params
        $params = $this->parseParams();
        $returnType = $this->advanceIfPunct(Token::COLON) ? $this->parseType() : null;
        $body = $this->atPunct(Token::BRACE_OPEN) ? $this->parseBlock() : null;
        $this->advanceIfPunct(Token::SEMICOLON); // an overload or abstract signature has no body

        return ['params' => $params, 'returnType' => $returnType, 'body' => $body];
    }

    private function fieldTail(string $name, Modifiers $modifiers): FieldDecl
    {
        $optional = $this->advanceIfPunct(Token::QUESTION);
        $this->advanceIfPunct(Token::BANG); // definite-assignment assertion
        $type = $this->advanceIfPunct(Token::COLON) ? $this->parseType() : null;

        if (! $this->advanceIfPunct(Token::ASSIGN)) {
            $this->consumeToStatementEnd();

            return new FieldDecl($name, $type, $modifiers, null, $optional);
        }

        return new FieldDecl($name, $type, $modifiers, $this->expressionToStatementEnd(), $optional);
    }

    /**
     * The keywords in front of a member. A keyword immediately followed by `(`, `=` or `:` is the
     * member's own NAME rather than a modifier — a field really called `static` is legal TypeScript.
     */
    private function memberModifiers(): Modifiers
    {
        $keywords = [];

        while ($this->peek()->isIdentifier() && in_array($this->peek()->value, Modifiers::KEYWORDS, true)) {
            if ($this->at(1)->isPunct(Token::PAREN_OPEN) || $this->at(1)->isPunct(Token::ASSIGN) || $this->at(1)->isPunct(Token::COLON)) {
                break;
            }

            $keywords[] = $this->advance()->value;
        }

        return new Modifiers($keywords);
    }

    /**
     * The `get`/`set` of an accessor — empty for an ordinary member, and for a member NAMED `get`.
     */
    private function memberAccessor(): string
    {
        if (($this->atId(Keyword::GET) || $this->atId(Keyword::SET)) && $this->at(1)->isIdentifier()) {
            return $this->advance()->value;
        }

        return '';
    }

    // ---- expressions ----------------------------------------------------------

    /**
     * The expression running to the first of $stops, as a real {@see Expr} tree — the seam between
     * this grammar and {@see ExprParser}, which owns the JS expression language for BOTH engines.
     * The slice's own offset is handed over, so every node inside it knows where it sits in the file.
     *
     * @param  list<string>  $stops
     */
    private function expressionUntil(array $stops): Expr
    {
        $start = $this->peek()->start;

        return $this->expressionBetween($start, $this->consumeExpression($stops));
    }

    /**
     * The expression running to the end of this statement, with the terminating `;` left off it.
     */
    private function expressionToStatementEnd(): Expr
    {
        $start = $this->peek()->start;

        return $this->expressionBetween($start, $this->consumeToStatementEnd());
    }

    private function expressionBetween(int $start, int $end): Expr
    {
        $text = rtrim(rtrim(substr($this->source, $start, max(0, $end - $start))), ';');

        return ExprParser::parse($text, $this->base + $start);
    }

    /**
     * A `( … )` head — the test of an `if`/`while`, the subject of a `switch`.
     */
    private function parenthesisedExpression(): Expr
    {
        $this->expectPunct(Token::PAREN_OPEN);
        $expression = $this->expressionUntil([Token::PAREN_CLOSE]);
        $this->advanceIfPunct(Token::PAREN_CLOSE);

        return $expression;
    }

    // ---- calls ----------------------------------------------------------------

    private function tryCall(): ?CallExpr
    {
        return $this->speculate(function (): CallExpr {
            $callee = $this->qualifiedName();
            $typeArguments = $this->atPunct(Token::ANGLE_OPEN) ? $this->parseTypeArguments() : [];
            $arguments = $this->atPunct(Token::PAREN_OPEN) ? $this->parseArguments() : [];

            return new CallExpr($callee, $typeArguments, $arguments);
        });
    }

    /**
     * @return list<string>  raw source of each argument
     */
    private function parseArguments(): array
    {
        $this->advance(); // `(`
        $arguments = [];

        while ($this->insideGroupClosedBy(Token::PAREN_CLOSE)) {
            $start = $this->peek()->start;
            $end = $this->consumeExpression([',', ')']);
            $arguments[] = trim(substr($this->source, $start, $end - $start));

            if ($this->atPunct(Token::COMMA)) {
                $this->advance();
            }
        }

        $this->advanceIfPunct(Token::PAREN_CLOSE);

        return $arguments;
    }

    // ---- cursor + skipping ----------------------------------------------------

    /**
     * Is there more to read inside a group that ends with $closer? The loop condition every
     * bracketed list shares — stop AT the closer, and stop at EOF too, so a group whose closer never
     * arrives cannot spin.
     */
    private function insideGroupClosedBy(string $closer): bool
    {
        return ! $this->atPunct($closer) && ! $this->eof();
    }

    /**
     * Does a CALL start here — a name followed by `(`, or by the `<` of its type arguments? The
     * shape a macro (`defineProps<…>()`) and a plain composable call have in common.
     */
    private function atCall(): bool
    {
        return $this->peek()->isIdentifier()
            && ($this->at(1)->isPunct(Token::PAREN_OPEN) || $this->at(1)->isPunct(Token::ANGLE_OPEN));
    }

    /**
     * Read $parse from where we stand, rewinding to it when the guess turns out to be wrong.
     *
     * The grammar is ambiguous in several places — `(a: T) => R` against a parenthesised type, a
     * call against a bare name — so reading it means guessing and being able to take the guess
     * back. This is the ONE place the cursor moves backwards, so a speculative read cannot forget
     * to rewind; on {@see Unparsed} the caller is handed null, standing exactly where it started.
     *
     * @template T
     * @param  callable(): T  $parse
     * @return T|null
     */
    private function speculate(callable $parse): mixed
    {
        $start = $this->pos;

        try {
            return $parse();
        } catch (Unparsed) {
            $this->pos = $start;

            return null;
        }
    }

    private function qualifiedName(): string
    {
        $name = $this->advance()->value;

        while ($this->atPunct(Token::DOT) && $this->at(1)->isIdentifier()) {
            $this->advance();
            $name .= '.' . $this->advance()->value;
        }

        return $name;
    }

    /**
     * Consume a balanced expression up to (not including) a top-level $stops punctuator, respecting
     * `()[]{}` nesting; returns the byte offset where it stopped. Used for initializers and call
     * arguments — the raw source is sliced from the span.
     *
     * @param  list<string>  $stops
     */
    private function consumeExpression(array $stops): int
    {
        $depth = 0;
        $end = $this->peek()->start;

        while (! $this->eof()) {
            $token = $this->peek();

            if ($depth === 0 && $token->isPunct() && in_array($token->value, $stops, true)) {
                break;
            }

            if ($token->isGroupOpener()) {
                $depth++;
            } elseif ($token->isGroupCloser()) {
                $depth--;
            }

            $end = $token->end;
            $this->advance();
        }

        return $end;
    }

    /**
     * Read an unmodelled type region VERBATIM from the current position — bracket-balanced across
     * `()[]{}<>` and NOT breaking at `=>` — until a top-level type terminator. Returns the source
     * text; the parser is left just past it. This is the total fallback.
     */
    private function captureTypeVerbatim(): string
    {
        $depth = 0;
        $start = $this->peek()->start;
        $end = $start;
        $prev = null;

        while (! $this->eof()) {
            $token = $this->peek();

            // The `=>` arrow is two tokens; its `>` is NOT a type close. Inside a nested region (a
            // function-type param list, an object member) consume both intact so the `>` never
            // corrupts the depth count (the bug this whole rewrite exists to kill). At depth 0 an
            // `=>` instead ENDS the type — it is the arrow of the enclosing arrow function — so we
            // let the `=` terminator below stop us, leaving the arrow for the caller.
            if ($depth > 0 && $token->isPunct(Token::ASSIGN) && $this->at(1)->isPunct(Token::ANGLE_CLOSE)) {
                $end = $this->at(1)->end;
                $this->advance();
                $this->advance();

                continue;
            }

            if ($depth === 0) {
                if ($token->isPunct() && in_array($token->value, [',', ';', ')', ']', '}', '>'], true)) {
                    break; // a terminator, or the `>` that closes an enclosing type-argument list
                }

                if ($token->isPunct(Token::ASSIGN)) {
                    break; // an initializer `=` (the arrow is handled above)
                }

                // A `{` that OPENS the type (an object/mapped type, first token or after a type
                // operator like `keyof`/`&`) is part of it; but a `{` after a COMPLETE type is the
                // enclosing function/arrow BODY, not the type — stop before it.
                if ($token->isPunct(Token::BRACE_OPEN) && $prev !== null && $this->completesType($prev)) {
                    break;
                }
            }

            if ($token->isTypeOpener()) {
                $depth++;
            } elseif ($token->isTypeCloser()) {
                $depth--;
            }

            $end = $token->end;
            $prev = $token;
            $this->advance();
        }

        return trim(substr($this->source, $start, $end - $start));
    }

    private function skipStatement(): void
    {
        if ($this->atPunct(Token::BRACE_OPEN)) {
            $this->skipBlock();

            return;
        }

        $this->consumeToStatementEnd();
    }

    private function skipBlock(): void
    {
        if (! $this->atPunct(Token::BRACE_OPEN)) {
            return;
        }

        $depth = 0;

        while (! $this->eof()) {
            $token = $this->advance();

            if ($token->isPunct(Token::BRACE_OPEN)) {
                $depth++;
            } elseif ($token->isPunct(Token::BRACE_CLOSE) && --$depth === 0) {
                return;
            }
        }
    }

    private function skipDefaultValue(): void
    {
        if ($this->atPunct(Token::ASSIGN) && ! $this->at(1)->isPunct(Token::ANGLE_CLOSE)) {
            $this->advance();
            $this->consumeExpression([',', ')', '}', ']']);
        }
    }

    private function consumeMemberVerbatim(): void
    {
        $depth = 0;

        while (! $this->eof()) {
            $token = $this->peek();

            if ($token->isPunct(Token::ASSIGN) && $this->at(1)->isPunct(Token::ANGLE_CLOSE)) {
                $this->advance();
                $this->advance(); // the `=>` arrow, kept from corrupting the depth

                continue;
            }

            if ($depth === 0 && $token->isPunct() && in_array($token->value, [';', ',', '}'], true)) {
                return;
            }

            if ($token->isTypeOpener()) {
                $depth++;
            } elseif ($token->isTypeCloser()) {
                if ($depth === 0 && $token->isPunct(Token::BRACE_CLOSE)) {
                    return;
                }

                $depth--;
            }

            $this->advance();
        }
    }

    /**
     * Advance to the end of the current statement — a top-level `;` or a newline gap — respecting
     * `()[]{}` nesting. Returns the byte offset just past the last consumed content.
     */
    private function consumeToStatementEnd(): int
    {
        $depth = 0;
        $end = $this->peek()->start;
        $previousEnd = null;

        while (! $this->eof()) {
            $token = $this->peek();

            if ($depth === 0) {
                if ($token->isPunct(Token::SEMICOLON)) {
                    $end = $token->end;
                    $this->advance();
                    break;
                }

                if ($previousEnd !== null && str_contains(substr($this->source, $previousEnd, $token->start - $previousEnd), "\n")) {
                    break; // a newline ends the statement (ASI)
                }
            }

            if ($token->isGroupOpener()) {
                $depth++;
            } elseif ($token->isGroupCloser()) {
                $depth--;
            }

            $end = $token->end;
            $previousEnd = $token->end;
            $this->advance();
        }

        return $end;
    }

    private function consumeUntilPunct(string $value): string
    {
        $start = $this->peek()->start;
        $end = $start;

        while (! $this->eof() && ! $this->atPunct($value)) {
            $end = $this->advance()->end;
        }

        return trim(substr($this->source, $start, $end - $start));
    }

    /**
     * Does this token COMPLETE a type — so a `{` right after it is a new construct (a function body),
     * not a continuation? A type name, a string/number literal type, or a closing `]`/`>`/`)` does;
     * a type-operator keyword (`keyof …`) does not (it still awaits its operand).
     */
    private function completesType(Lexeme $token): bool
    {
        if ($token->isIdentifier()) {
            return ! in_array($token->value, self::TYPE_OPERATORS, true);
        }

        return $token->is(Token::STRING) || $token->is(Token::NUMBER)
            || $token->isPunct(Token::BRACKET_CLOSE) || $token->isPunct(Token::ANGLE_CLOSE) || $token->isPunct(Token::PAREN_CLOSE);
    }

    /**
     * Is the current `readonly` the property MODIFIER, not a property literally NAMED `readonly`?
     * It's the modifier only before a real member name — never before `?`/`:`/`(`, which mark
     * `readonly` itself as the (optional / typed / method) property.
     */
    private function atReadonlyModifier(): bool
    {
        if (! $this->atId('readonly')) {
            return false;
        }

        $next = $this->at(1);

        return ! $next->isNone() && ! ($next->isPunct(Token::QUESTION) || $next->isPunct(Token::COLON) || $next->isPunct(Token::PAREN_OPEN));
    }

    private function atThreeDots(): bool
    {
        return $this->atPunct(Token::DOT) && $this->at(1)->isPunct(Token::DOT) && $this->at(2)->isPunct(Token::DOT);
    }

    private function advanceIfThreeDots(): bool
    {
        if (! $this->atThreeDots()) {
            return false;
        }

        $this->advance();
        $this->advance();
        $this->advance();

        return true;
    }

    /**
     * The token the cursor is on — {@see Lexeme::none} past the end, never null, so a lookahead
     * question is asked straight rather than through a `?->… ?? false`.
     */
    private function peek(): Lexeme
    {
        return $this->at(0);
    }

    /**
     * The token $offset ahead of the cursor — {@see Lexeme::none} past the end.
     */
    private function at(int $offset): Lexeme
    {
        return $this->lexemes[$this->pos + $offset] ?? Lexeme::none(strlen($this->source));
    }

    private function advance(): Lexeme
    {
        $token = $this->lexemes[$this->pos] ?? throw new Unparsed();
        $this->pos++;

        return $token;
    }

    private function eof(): bool
    {
        return $this->pos >= count($this->lexemes);
    }

    private function atId(string $value): bool
    {
        return $this->peek()->isIdentifier($value);
    }

    private function atPunct(string $value): bool
    {
        return $this->peek()->isPunct($value);
    }

    private function expectPunct(string $value): void
    {
        if (! $this->advanceIfPunct($value)) {
            throw new Unparsed();
        }
    }

    private function advanceIfPunct(string $value): bool
    {
        if ($this->atPunct($value)) {
            $this->advance();

            return true;
        }

        return false;
    }

    private function advanceIfId(string $value): bool
    {
        if ($this->atId($value)) {
            $this->advance();

            return true;
        }

        return false;
    }
}
