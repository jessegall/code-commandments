<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts;

use JesseGall\CodeCommandments\Vue\Token;
use JesseGall\CodeCommandments\Vue\Ts\Node\ArrayPattern;
use JesseGall\CodeCommandments\Vue\Ts\Node\ArrayType;
use JesseGall\CodeCommandments\Vue\Ts\Node\CallExpr;
use JesseGall\CodeCommandments\Vue\Ts\Node\CompositeType;
use JesseGall\CodeCommandments\Vue\Ts\Node\FunctionDecl;
use JesseGall\CodeCommandments\Vue\Ts\Node\FunctionType;
use JesseGall\CodeCommandments\Vue\Ts\Node\ImportDecl;
use JesseGall\CodeCommandments\Vue\Ts\Node\IndexedAccessType;
use JesseGall\CodeCommandments\Vue\Ts\Node\InterfaceDecl;
use JesseGall\CodeCommandments\Vue\Ts\Node\KeywordType;
use JesseGall\CodeCommandments\Vue\Ts\Node\LiteralType;
use JesseGall\CodeCommandments\Vue\Ts\Node\Member;
use JesseGall\CodeCommandments\Vue\Ts\Node\Method;
use JesseGall\CodeCommandments\Vue\Ts\Node\Module;
use JesseGall\CodeCommandments\Vue\Ts\Node\NamedType;
use JesseGall\CodeCommandments\Vue\Ts\Node\NamePattern;
use JesseGall\CodeCommandments\Vue\Ts\Node\Node;
use JesseGall\CodeCommandments\Vue\Ts\Node\ObjectPattern;
use JesseGall\CodeCommandments\Vue\Ts\Node\ObjectType;
use JesseGall\CodeCommandments\Vue\Ts\Node\Param;
use JesseGall\CodeCommandments\Vue\Ts\Node\ParenType;
use JesseGall\CodeCommandments\Vue\Ts\Node\Pattern;
use JesseGall\CodeCommandments\Vue\Ts\Node\Property;
use JesseGall\CodeCommandments\Vue\Ts\Node\TupleType;
use JesseGall\CodeCommandments\Vue\Ts\Node\TypeAliasDecl;
use JesseGall\CodeCommandments\Vue\Ts\Node\TypeNode;
use JesseGall\CodeCommandments\Vue\Ts\Node\TypeofType;
use JesseGall\CodeCommandments\Vue\Ts\Node\VariableDecl;
use JesseGall\CodeCommandments\Vue\Ts\Node\VerbatimType;

/**
 * The recursive-descent parser for a `<script setup>` — tokens ({@see Lexer}) → a {@see Module}
 * tree. It models the constructs real components use: imports, `interface`/`type` declarations,
 * `const`/`let`/`var` (with destructuring and a structured initializer call), `function`s, macro and
 * composable calls, and the full TYPE grammar. The type grammar is precedence-respecting — union →
 * intersection → postfix (`[]`, indexed) → primary (paren/function/object/tuple/ref/keyword/literal/
 * typeof) — so the arrow of a function type is a first-class production and never truncates.
 *
 * "Can't fail": anything the grammar doesn't model (a conditional/mapped/template-literal type, an
 * exotic statement) is preserved via a bracket-balanced verbatim capture ({@see Unparsed} →
 * {@see VerbatimType}, {@see skipStatement}) rather than mis-parsed. The parser is total.
 */
final class Parser
{
    private const array KEYWORD_TYPES = [
        'string', 'number', 'boolean', 'void', 'unknown', 'never', 'any', 'null', 'undefined',
        'object', 'symbol', 'bigint', 'this',
    ];

    /** Type-level leads the grammar doesn't model — bail to verbatim so the whole region is kept. */
    private const array VERBATIM_LEADS = ['keyof', 'readonly', 'infer', 'unique', 'new', 'abstract', 'asserts'];

    /** @var list<Lexeme> */
    private array $lexemes;

    private int $pos = 0;

    private function __construct(private readonly string $source)
    {
        $this->lexemes = new Lexer()->tokenize($source);
    }

    public static function module(string $source): Module
    {
        return new self($source)->parseModule();
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

    private function parseStatement(): ?Node
    {
        // `import` DECLARATION only — not `import.meta` / dynamic `import(…)`, which are expressions.
        if ($this->atId('import') && ! ($this->at(1)?->isPunct('.') ?? false) && ! ($this->at(1)?->isPunct('(') ?? false)) {
            return $this->parseImport();
        }

        if ($this->atId('export')) {
            $this->advance(); // `export` — parse the declaration it fronts

            return $this->parseStatement();
        }

        if ($this->atId('interface')) {
            return $this->parseInterface();
        }

        if ($this->atId('type') && ($this->at(1)?->isIdentifier() ?? false)) {
            return $this->parseTypeAlias();
        }

        if ($this->atId('const') || $this->atId('let') || $this->atId('var')) {
            return $this->parseVariable();
        }

        if ($this->atId('function') || ($this->atId('async') && ($this->at(1)?->isIdentifier('function') ?? false))) {
            return $this->parseFunction();
        }

        // A bare top-level call — a macro (`defineProps<…>()`) or a side-effect call.
        if ($this->peek()?->isIdentifier() && ($this->at(1)?->isPunct('(') || $this->at(1)?->isPunct('<'))) {
            $call = $this->tryCall();

            if ($call !== null) {
                return $call;
            }
        }

        $this->skipStatement();

        return null;
    }

    // ---- declarations ---------------------------------------------------------

    private function parseImport(): ImportDecl
    {
        $start = $this->peek()?->start ?? 0;
        $bindings = [];
        $source = null;
        $typeOnly = false;
        $this->advance(); // `import`

        if ($this->atId('type')) {
            $typeOnly = true;
            $this->advance();
        }

        // `import X = App.Http.View.Page;` (TS import-equals alias)
        if ($this->peek()?->isIdentifier() && ($this->at(1)?->isPunct('=') ?? false)) {
            $local = $this->advance()->value;
            $this->advance(); // `=`
            $bindings[$local] = $this->qualifiedName();
        } else {
            $bindings = $this->parseImportBindings();

            if ($this->atId('from')) {
                $this->advance();
                $source = $this->peek()?->is(Token::STRING) ? substr($this->advance()->value, 1, -1) : null;
            } elseif ($this->peek()?->is(Token::STRING)) {
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

        if ($this->peek()?->isIdentifier() && ! $this->atPunct('{') && ! $this->atPunct('*')) {
            $bindings[$this->advance()->value] = 'default';

            if ($this->atPunct(',')) {
                $this->advance();
            }
        }

        if ($this->atPunct('*')) {
            $this->advance();
            $this->advanceIfId('as');
            $bindings[$this->advance()->value] = '*';
        }

        if ($this->atPunct('{')) {
            $this->advance();

            while (! $this->atPunct('}') && ! $this->eof()) {
                $this->advanceIfId('type');
                $imported = $this->advance()->value;
                $local = $this->advanceIfId('as') ? $this->advance()->value : $imported;
                $bindings[$local] = $imported;

                if ($this->atPunct(',')) {
                    $this->advance();
                }
            }

            $this->advanceIfPunct('}');
        }

        return $bindings;
    }

    private function parseInterface(): InterfaceDecl
    {
        $this->advance(); // `interface`
        $name = $this->advance()->value;
        $header = $this->consumeUntilPunct('{'); // type params + extends clause, kept verbatim

        return new InterfaceDecl($name, $this->parseTypeMembers(strict: false), $header);
    }

    private function parseTypeAlias(): TypeAliasDecl
    {
        $this->advance(); // `type`
        $name = $this->advance()->value;
        $header = $this->consumeUntilPunct('='); // type params, kept verbatim
        $this->advanceIfPunct('=');
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

        if ($this->atPunct(':')) {
            $this->advance();
            $type = $this->parseType();
        }

        if ($this->atPunct('=')) {
            $this->advance();
            $this->advanceIfId('await'); // `= await useX()` — trace through to the call
            $initStart = $this->peek()?->start ?? 0;

            if ($this->peek()?->isIdentifier() && ($this->at(1)?->isPunct('(') || $this->at(1)?->isPunct('<'))) {
                $initCall = $this->tryCall();
            } elseif ($this->atPunct('(')) {
                [$initParams, $initReturnType] = $this->tryArrowSignature();
            }

            $initEnd = $this->consumeToStatementEnd();
            $initRaw = trim(substr($this->source, $initStart, $initEnd - $initStart));
        } else {
            $this->consumeToStatementEnd();
        }

        return new VariableDecl($keyword, $pattern, $type, $initRaw, $initCall, $initParams, $initReturnType);
    }

    private function parseFunction(): FunctionDecl
    {
        $this->advanceIfId('async');
        $this->advance(); // `function`
        $this->advanceIfPunct('*'); // generator
        $name = $this->advance()->value;
        $this->consumeUntilPunct('('); // type params
        $params = $this->parseParams();
        $returnType = null;

        if ($this->atPunct(':')) {
            $this->advance();
            $returnType = $this->parseType();
        }

        $this->skipBlock(); // the body `{ … }`

        return new FunctionDecl($name, $params, $returnType);
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
        $start = $this->pos;

        try {
            $params = $this->parseParams();
            $returnType = $this->advanceIfPunct(':') ? $this->parseType() : null;

            if ($this->atPunct('=') && ($this->at(1)?->isPunct('>') ?? false)) {
                return [$params, $returnType];
            }
        } catch (Unparsed) {
            // not an arrow — fall through
        }

        $this->pos = $start;

        return [null, null];
    }

    private function parsePattern(): Pattern
    {
        if ($this->atPunct('{')) {
            return $this->parseObjectPattern();
        }

        if ($this->atPunct('[')) {
            return $this->parseArrayPattern();
        }

        return new NamePattern($this->advance()->value);
    }

    private function parseObjectPattern(): ObjectPattern
    {
        $this->advance(); // `{`
        $entries = [];
        $rest = null;

        while (! $this->atPunct('}') && ! $this->eof()) {
            if ($this->atThreeDots()) {
                $rest = $this->advance()->value;
            } else {
                $key = $this->advance()->value;
                $local = $this->advanceIfPunct(':') ? $this->advance()->value : $key;
                $entries[$local] = $key;
                $this->skipDefaultValue();
            }

            if ($this->atPunct(',')) {
                $this->advance();
            }
        }

        $this->advanceIfPunct('}');

        return new ObjectPattern($entries, $rest);
    }

    private function parseArrayPattern(): ArrayPattern
    {
        $this->advance(); // `[`
        $elements = [];

        while (! $this->atPunct(']') && ! $this->eof()) {
            if ($this->atPunct(',')) {
                $elements[] = null; // a hole
                $this->advance();

                continue;
            }

            $this->advanceIfThreeDots();
            $elements[] = $this->advance()->value;
            $this->skipDefaultValue();

            if ($this->atPunct(',')) {
                $this->advance();
            }
        }

        $this->advanceIfPunct(']');

        return new ArrayPattern($elements);
    }

    // ---- type grammar ---------------------------------------------------------

    private function parseType(): TypeNode
    {
        $start = $this->pos;

        try {
            $type = $this->parseUnion();

            if ($this->atId('extends')) { // a conditional type — not modelled; keep whole region
                throw new Unparsed();
            }

            return $type;
        } catch (Unparsed) {
            $this->pos = $start;

            return new VerbatimType($this->captureTypeVerbatim());
        }
    }

    private function parseUnion(): TypeNode
    {
        $this->advanceIfPunct('|'); // a leading `|` is allowed
        $members = [$this->parseIntersection()];

        while ($this->atPunct('|')) {
            $this->advance();
            $members[] = $this->parseIntersection();
        }

        return count($members) === 1 ? $members[0] : new CompositeType('|', $members);
    }

    private function parseIntersection(): TypeNode
    {
        $this->advanceIfPunct('&');
        $members = [$this->parsePostfix()];

        while ($this->atPunct('&')) {
            $this->advance();
            $members[] = $this->parsePostfix();
        }

        return count($members) === 1 ? $members[0] : new CompositeType('&', $members);
    }

    private function parsePostfix(): TypeNode
    {
        $type = $this->parsePrimary();

        while ($this->atPunct('[')) {
            $this->advance();

            if ($this->atPunct(']')) {
                $this->advance();
                $type = new ArrayType($type);
            } else {
                $index = $this->parseType();
                $this->expectPunct(']');
                $type = new IndexedAccessType($type, $index);
            }
        }

        return $type;
    }

    private function parsePrimary(): TypeNode
    {
        $token = $this->peek();

        if ($token === null) {
            throw new Unparsed();
        }

        if ($token->isPunct('(')) {
            return $this->parseParenOrFunction();
        }

        if ($token->isPunct('{')) {
            return new ObjectType($this->parseTypeMembers(strict: true));
        }

        if ($token->isPunct('[')) {
            return $this->parseTuple();
        }

        if ($token->isPunct('-') && ($this->at(1)?->is(Token::NUMBER) ?? false)) {
            $this->advance();

            return new LiteralType('-' . $this->advance()->value);
        }

        if ($token->is(Token::STRING) || $token->is(Token::NUMBER)) {
            return new LiteralType($this->advance()->value);
        }

        if ($token->isIdentifier('typeof')) {
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

        if ($this->atPunct('<')) {
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
        $start = $this->pos;

        try {
            $params = $this->parseParams();

            if ($this->atPunct('=') && ($this->at(1)?->isPunct('>') ?? false)) {
                $this->advance();
                $this->advance(); // `=>`

                return new FunctionType($params, $this->parseType());
            }
        } catch (Unparsed) {
            // fall through to the parenthesised-type reading
        }

        $this->pos = $start;
        $this->expectPunct('(');
        $inner = $this->parseType();
        $this->expectPunct(')');

        return new ParenType($inner);
    }

    private function parseTuple(): TupleType
    {
        $this->advance(); // `[`
        $elements = [];

        while (! $this->atPunct(']') && ! $this->eof()) {
            $elements[] = $this->parseType();

            if ($this->atPunct(',')) {
                $this->advance();
            }
        }

        $this->expectPunct(']');

        return new TupleType($elements);
    }

    /**
     * @return list<TypeNode>
     */
    private function parseTypeArguments(): array
    {
        $this->advance(); // `<`
        $arguments = [];

        while (! $this->atPunct('>') && ! $this->eof()) {
            $arguments[] = $this->parseType();

            if ($this->atPunct(',')) {
                $this->advance();
            }
        }

        $this->expectPunct('>');

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
        $this->expectPunct('{');
        $members = [];

        while (! $this->atPunct('}') && ! $this->eof()) {
            $named = $this->peek()?->isIdentifier() || ($this->peek()?->is(Token::STRING) ?? false) || $this->atId('readonly');

            if (! $named) {
                if ($strict) {
                    throw new Unparsed();
                }

                $this->consumeMemberVerbatim();
                $this->advanceIfPunct(';');
                $this->advanceIfPunct(',');

                continue;
            }

            $members[] = $this->parseTypeMember($strict);
            $this->advanceIfPunct(';');
            $this->advanceIfPunct(',');
        }

        $this->expectPunct('}');

        return $members;
    }

    private function parseTypeMember(bool $strict): Member
    {
        $this->advanceIfId('readonly');
        $name = $this->advance()->value;
        $optional = $this->advanceIfPunct('?');

        if ($this->atPunct('(')) {
            $params = $this->parseParams();
            $returnType = $this->advanceIfPunct(':') ? $this->parseType() : new KeywordType('void');

            return new Method($name, $params, $returnType, $optional);
        }

        if ($this->advanceIfPunct(':')) {
            return new Property($name, $this->parseType(), $optional);
        }

        throw new Unparsed(); // a member shape we don't model — keep the whole object verbatim
    }

    /**
     * @return list<Param>
     */
    private function parseParams(): array
    {
        $this->expectPunct('(');
        $params = [];

        while (! $this->atPunct(')') && ! $this->eof()) {
            $rest = $this->advanceIfThreeDots();

            if (! $this->peek()?->isIdentifier() && ! $this->atPunct('{') && ! $this->atPunct('[')) {
                throw new Unparsed(); // a param we can't name (destructured param type) — bail
            }

            $name = $this->atPunct('{') || $this->atPunct('[') ? $this->parsePattern()->render() : $this->advance()->value;
            $optional = $this->advanceIfPunct('?');
            $type = $this->advanceIfPunct(':') ? $this->parseType() : null;
            $this->skipDefaultValue();
            $params[] = new Param($name, $type, $optional, $rest);

            if ($this->atPunct(',')) {
                $this->advance();
            }
        }

        $this->expectPunct(')');

        return $params;
    }

    // ---- calls ----------------------------------------------------------------

    private function tryCall(): ?CallExpr
    {
        $start = $this->pos;

        try {
            $callee = $this->qualifiedName();
            $typeArguments = $this->atPunct('<') ? $this->parseTypeArguments() : [];
            $arguments = $this->atPunct('(') ? $this->parseArguments() : [];

            return new CallExpr($callee, $typeArguments, $arguments);
        } catch (Unparsed) {
            $this->pos = $start;

            return null;
        }
    }

    /**
     * @return list<string>  raw source of each argument
     */
    private function parseArguments(): array
    {
        $this->advance(); // `(`
        $arguments = [];

        while (! $this->atPunct(')') && ! $this->eof()) {
            $start = $this->peek()?->start ?? 0;
            $end = $this->consumeExpression([',', ')']);
            $arguments[] = trim(substr($this->source, $start, $end - $start));

            if ($this->atPunct(',')) {
                $this->advance();
            }
        }

        $this->advanceIfPunct(')');

        return $arguments;
    }

    // ---- cursor + skipping ----------------------------------------------------

    private function qualifiedName(): string
    {
        $name = $this->advance()->value;

        while ($this->atPunct('.') && ($this->at(1)?->isIdentifier() ?? false)) {
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
        $end = $this->peek()?->start ?? strlen($this->source);

        while (! $this->eof()) {
            $token = $this->peek();

            if ($depth === 0 && $token->isPunct() && in_array($token->value, $stops, true)) {
                break;
            }

            if ($token->isPunct() && Token::opensGroup($token->value)) {
                $depth++;
            } elseif ($token->isPunct() && Token::closesGroup($token->value)) {
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
        $start = $this->peek()?->start ?? 0;
        $end = $start;

        while (! $this->eof()) {
            $token = $this->peek();

            // The `=>` arrow is two tokens; its `>` is NOT a type close. Consume both intact so it
            // never corrupts the depth count (the bug this whole rewrite exists to kill).
            if ($token->isPunct('=') && ($this->at(1)?->isPunct('>') ?? false)) {
                $end = $this->at(1)->end;
                $this->advance();
                $this->advance();

                continue;
            }

            if ($depth === 0) {
                if ($token->isPunct() && in_array($token->value, [',', ';', ')', ']', '}', '>'], true)) {
                    break; // a terminator, or the `>` that closes an enclosing type-argument list
                }

                if ($token->isPunct('=')) {
                    break; // an initializer `=` (the arrow is handled above)
                }
            }

            if ($token->isPunct() && Token::opensType($token->value)) {
                $depth++;
            } elseif ($token->isPunct() && Token::closesType($token->value)) {
                $depth--;
            }

            $end = $token->end;
            $this->advance();
        }

        return trim(substr($this->source, $start, $end - $start));
    }

    private function skipStatement(): void
    {
        if ($this->atPunct('{')) {
            $this->skipBlock();

            return;
        }

        $this->consumeToStatementEnd();
    }

    private function skipBlock(): void
    {
        if (! $this->atPunct('{')) {
            return;
        }

        $depth = 0;

        while (! $this->eof()) {
            $token = $this->advance();

            if ($token->isPunct('{')) {
                $depth++;
            } elseif ($token->isPunct('}') && --$depth === 0) {
                return;
            }
        }
    }

    private function skipDefaultValue(): void
    {
        if ($this->atPunct('=') && ! ($this->at(1)?->isPunct('>') ?? false)) {
            $this->advance();
            $this->consumeExpression([',', ')', '}', ']']);
        }
    }

    private function consumeMemberVerbatim(): void
    {
        $depth = 0;

        while (! $this->eof()) {
            $token = $this->peek();

            if ($token->isPunct('=') && ($this->at(1)?->isPunct('>') ?? false)) {
                $this->advance();
                $this->advance(); // the `=>` arrow, kept from corrupting the depth

                continue;
            }

            if ($depth === 0 && $token->isPunct() && in_array($token->value, [';', ',', '}'], true)) {
                return;
            }

            if ($token->isPunct() && Token::opensType($token->value)) {
                $depth++;
            } elseif ($token->isPunct() && Token::closesType($token->value)) {
                if ($depth === 0 && $token->isPunct('}')) {
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
        $end = $this->peek()?->start ?? strlen($this->source);
        $previousEnd = null;

        while (! $this->eof()) {
            $token = $this->peek();

            if ($depth === 0) {
                if ($token->isPunct(';')) {
                    $end = $token->end;
                    $this->advance();
                    break;
                }

                if ($previousEnd !== null && str_contains(substr($this->source, $previousEnd, $token->start - $previousEnd), "\n")) {
                    break; // a newline ends the statement (ASI)
                }
            }

            if ($token->isPunct() && Token::opensGroup($token->value)) {
                $depth++;
            } elseif ($token->isPunct() && Token::closesGroup($token->value)) {
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
        $start = $this->peek()?->start ?? 0;
        $end = $start;

        while (! $this->eof() && ! $this->atPunct($value)) {
            $end = $this->advance()->end;
        }

        return trim(substr($this->source, $start, $end - $start));
    }

    private function atThreeDots(): bool
    {
        return $this->atPunct('.') && ($this->at(1)?->isPunct('.') ?? false) && ($this->at(2)?->isPunct('.') ?? false);
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

    private function peek(): ?Lexeme
    {
        return $this->lexemes[$this->pos] ?? null;
    }

    private function at(int $offset): ?Lexeme
    {
        return $this->lexemes[$this->pos + $offset] ?? null;
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
        return $this->peek()?->isIdentifier($value) ?? false;
    }

    private function atPunct(string $value): bool
    {
        return $this->peek()?->isPunct($value) ?? false;
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
