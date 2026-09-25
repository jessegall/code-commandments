<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use Closure;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Token;

/** One parsed file as the contract's nested nodes and its comments. */
final class TreeWriter
{
    /** Classes the file names, keyed by their lower-cased name: the ones reflection may find outside the scan. */
    public array $referenced = [];

    /** Class-likes the file declares, keyed by their lower-cased name. */
    public array $declared = [];

    /** @var list<array{start: int, end: int, id: int}> */
    private array $spans = [];

    private int $next = 0;

    /** @var array<int, int> */
    private array $commentEnds = [];

    private ?string $class = null;

    public function __construct(private readonly string $code) {}

    /**
     * The file's root: a `File` node spanning the whole file, its top-level statements in `stmts`.
     *
     * @param  list<Node>  $statements
     */
    public function root(array $statements): array
    {
        $id = $this->next++;
        $this->spans[] = ['start' => 0, 'end' => strlen($this->code), 'id' => $id];
        $root = ['id' => $id, 'kind' => 'File', 'role' => 'other', 'span' => [0, strlen($this->code), 1]];
        $children = array_map(fn (Node $statement): array => $this->node($statement, 'stmts'), $statements);
        if ($children !== []) {
            $root['children'] = $children;
        }

        return $root;
    }

    /** @param  list<Token>  $tokens */
    public function comments(array $tokens): array
    {
        $written = array_values(array_filter($tokens, static fn (Token $token): bool => in_array($token->id, [T_COMMENT, T_DOC_COMMENT], true)));
        foreach ($written as $token) {
            $this->commentEnds[$token->pos] = $token->pos + strlen($token->text);
        }
        $comments = [];
        foreach ($written as $token) {
            $text = rtrim($token->text, "\r\n");
            $comments[] = [
                'id' => count($comments),
                'kind' => $token->id === T_DOC_COMMENT ? 'doc' : (str_starts_with($token->text, '/*') ? 'block' : 'line'),
                'text' => $text,
                'span' => [$token->pos, $token->pos + strlen($text), $token->line],
            ] + $this->attachment($token->pos, $token->pos + strlen($token->text));
        }

        return $comments;
    }

    private function node(Node $node, string $field, ?Node $parent = null): array
    {
        $id = $this->next++;
        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos() + 1;
        $this->spans[] = ['start' => $start, 'end' => $end, 'id' => $id];
        $out = ['id' => $id, 'kind' => $node->getType(), 'role' => $this->role($node, $field, $parent)];
        $is = $this->neutral($node);
        if ($is !== []) {
            $out['is'] = $is;
        }
        $out['span'] = [$start, $end, $node->getStartLine()];
        $out['field'] = $field;
        $enclosing = $this->class;
        if ($node instanceof Node\Stmt\ClassLike) {
            $this->class = $node->namespacedName?->toString();
        }
        $out += $this->facts($node, $parent);
        $children = [];
        foreach ($node->getSubNodeNames() as $name) {
            $value = $node->$name;
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node) {
                    $children[] = $this->node($child, $name, $node);
                }
            }
        }
        $this->class = $enclosing;
        if ($children !== []) {
            $out['children'] = $children;
        }

        return $out;
    }

    private function role(Node $node, string $field, ?Node $parent): string
    {
        return match (true) {
            $node instanceof Node\Stmt\ClassLike, $node instanceof Node\Stmt\ClassMethod, $node instanceof Node\Stmt\Function_,
            $node instanceof Node\Stmt\Property, $node instanceof Node\Stmt\ClassConst, $node instanceof Node\Stmt\EnumCase => 'member',
            $node instanceof Node\Stmt => 'statement',
            $node instanceof Node\ComplexType, $parent instanceof Node\ComplexType, in_array($field, ['type', 'returnType'], true),
            $parent instanceof Node\Stmt\Catch_ && $field === 'types' => 'type',
            $node instanceof Node\Expr, $node instanceof Node\Scalar => 'expression',
            default => 'other',
        };
    }

    /** @return list<string> */
    private function neutral(Node $node): array
    {
        $answers = [
            'function' => $node instanceof Node\FunctionLike && ! ($node instanceof Node\Stmt\ClassMethod && $node->stmts === null),
            'type-declaration' => $node instanceof Node\Stmt\ClassLike,
            'parameter' => $node instanceof Node\Param,
            'branch' => $node instanceof Node\Stmt\If_ || $node instanceof Node\Stmt\ElseIf_ || $node instanceof Node\Stmt\Switch_
                || $node instanceof Node\Expr\Match_ || $node instanceof Node\Expr\Ternary,
            'loop' => $node instanceof Node\Stmt\For_ || $node instanceof Node\Stmt\Foreach_ || $node instanceof Node\Stmt\While_ || $node instanceof Node\Stmt\Do_,
            'return' => $node instanceof Node\Stmt\Return_,
            'throw' => $node instanceof Node\Expr\Throw_,
            'bail-out' => $node instanceof Node\Stmt\Return_ || $node instanceof Node\Stmt\Break_ || $node instanceof Node\Stmt\Continue_
                || ($node instanceof Node\Stmt\Expression && $node->expr instanceof Node\Expr\Throw_),
            'expression-statement' => $node instanceof Node\Stmt\Expression,
            'call' => $node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\NullsafeMethodCall || $node instanceof Node\Expr\StaticCall || $node instanceof Node\Expr\FuncCall,
            'construction' => $node instanceof Node\Expr\New_,
            'member-access' => $node instanceof Node\Expr\PropertyFetch || $node instanceof Node\Expr\NullsafePropertyFetch || $node instanceof Node\Expr\StaticPropertyFetch || $node instanceof Node\Expr\ClassConstFetch,
            'null-safe' => $node instanceof Node\Expr\NullsafeMethodCall || $node instanceof Node\Expr\NullsafePropertyFetch,
            'self-reference' => ($node instanceof Node\Expr\Variable && $node->name === 'this')
                || ($node instanceof Node\Name && $node->isSpecialClassName()),
            'identifier' => $node instanceof Node\Expr\Variable && $node->name !== 'this',
            'assignment' => $node instanceof Node\Expr\Assign || $node instanceof Node\Expr\AssignOp || $node instanceof Node\Expr\AssignRef,
            'comparison' => $node instanceof Node\Expr\BinaryOp\Identical || $node instanceof Node\Expr\BinaryOp\NotIdentical || $node instanceof Node\Expr\BinaryOp\Equal
                || $node instanceof Node\Expr\BinaryOp\NotEqual || $node instanceof Node\Expr\BinaryOp\Smaller || $node instanceof Node\Expr\BinaryOp\Greater
                || $node instanceof Node\Expr\BinaryOp\SmallerOrEqual || $node instanceof Node\Expr\BinaryOp\GreaterOrEqual,
            'literal' => $node instanceof Node\Scalar || $this->literal($node) !== [],
            'import' => $node instanceof Node\Stmt\Use_ || $node instanceof Node\Stmt\GroupUse,
            'catch' => $node instanceof Node\Stmt\Catch_,
        ];

        return array_keys(array_filter($answers));
    }

    private function facts(Node $node, ?Node $parent): array
    {
        $facts = [];
        $name = $this->nameOf($node);
        if ($name !== null) {
            $facts['name'] = $name;
        }
        $facts += $this->literal($node);
        $operator = $this->operator($node);
        if ($operator !== null) {
            $facts['operator'] = $operator;
        }
        $modifiers = $this->modifiers($node);
        if ($modifiers !== []) {
            $facts['modifiers'] = $modifiers;
        }
        $flags = $this->flags($node);
        if ($parent instanceof Node\Stmt\For_ && in_array($node, $parent->loop, true)) {
            $flags[] = 'step';
        }
        if ($flags !== []) {
            $facts['flags'] = $flags;
        }
        if (($node instanceof Node\Param || $node instanceof Node\Stmt\Property || $node instanceof Node\Stmt\ClassConst) && $node->type !== null) {
            $facts['declared'] = $this->type($node->type);
        }
        if ($node instanceof Node\Stmt\Catch_) {
            $facts['declared'] = $this->caught($node->types);
        }
        if ($node instanceof Node\FunctionLike && $node->getReturnType() !== null) {
            $facts['returns'] = $this->type($node->getReturnType());
        }
        $symbol = $this->symbolOf($node);
        if ($symbol !== null) {
            $facts['symbol'] = $symbol;
        }
        $refers = $this->refersOf($node, $parent);
        if ($refers !== null) {
            $facts['refers'] = $refers;
        }

        return $facts;
    }

    private function nameOf(Node $node): ?string
    {
        return match (true) {
            $node instanceof Node\Identifier, $node instanceof Node\Name => $node->toString(),
            $node instanceof Node\Expr\Variable && is_string($node->name) => $node->name,
            $node instanceof Node\Stmt\ClassLike, $node instanceof Node\Stmt\ClassMethod, $node instanceof Node\Stmt\Function_,
            $node instanceof Node\PropertyItem, $node instanceof Node\Const_, $node instanceof Node\Stmt\EnumCase,
            $node instanceof Node\PropertyHook => $node->name?->toString(),
            $node instanceof Node\Param && $node->var instanceof Node\Expr\Variable && is_string($node->var->name) => $node->var->name,
            default => null,
        };
    }

    private function literal(Node $node): array
    {
        return match (true) {
            $node instanceof Node\Scalar\String_ => ['literal' => 'string', 'value' => $node->value],
            $node instanceof Node\Scalar\Int_ => ['literal' => 'int', 'value' => (string) $node->value],
            $node instanceof Node\Scalar\Float_ => ['literal' => 'float', 'value' => $this->decimal($node->value)],
            $node instanceof Node\Scalar\InterpolatedString => ['literal' => 'interpolated'],
            $node instanceof Node\InterpolatedStringPart => ['literal' => 'string', 'value' => $node->value],
            $node instanceof Node\Expr\ConstFetch && in_array($node->name->toLowerString(), ['true', 'false'], true)
                => ['literal' => 'bool', 'value' => $node->name->toLowerString() === 'true'],
            $node instanceof Node\Expr\ConstFetch && $node->name->toLowerString() === 'null' => ['literal' => 'null', 'value' => null],
            default => [],
        };
    }

    private function decimal(float $value): string
    {
        if (is_nan($value) || is_infinite($value)) {
            return (string) $value;
        }
        $text = rtrim(rtrim(sprintf('%.17F', $value), '0'), '.');

        return (string) (float) $text === (string) $value ? $text : var_export($value, true);
    }

    private function operator(Node $node): ?string
    {
        return match (true) {
            $node instanceof Node\Expr\BinaryOp => $node->getOperatorSigil(),
            $node instanceof Node\Expr\Assign => '=',
            $node instanceof Node\Expr\AssignRef => '=&',
            $node instanceof Node\Expr\AssignOp => [
                'Plus' => '+', 'Minus' => '-', 'Mul' => '*', 'Div' => '/', 'Concat' => '.', 'Mod' => '%', 'Pow' => '**', 'Coalesce' => '??',
                'BitwiseAnd' => '&', 'BitwiseOr' => '|', 'BitwiseXor' => '^', 'ShiftLeft' => '<<', 'ShiftRight' => '>>',
            ][substr($node->getType(), strlen('Expr_AssignOp_'))] . '=',
            $node instanceof Node\Expr\PreInc, $node instanceof Node\Expr\PostInc => '++',
            $node instanceof Node\Expr\PreDec, $node instanceof Node\Expr\PostDec => '--',
            $node instanceof Node\Expr\UnaryMinus => '-',
            $node instanceof Node\Expr\UnaryPlus => '+',
            $node instanceof Node\Expr\BooleanNot => '!',
            $node instanceof Node\Expr\BitwiseNot => '~',
            $node instanceof Node\Expr\Instanceof_ => 'instanceof',
            default => null,
        };
    }

    /** @return list<string> */
    private function modifiers(Node $node): array
    {
        if ($node instanceof Node\Stmt\Use_ || $node instanceof Node\Stmt\GroupUse || $node instanceof Node\UseItem) {
            return match ($node->type) {
                Node\Stmt\Use_::TYPE_FUNCTION => ['function'],
                Node\Stmt\Use_::TYPE_CONSTANT => ['const'],
                default => [],
            };
        }
        $flags = match (true) {
            $node instanceof Node\Stmt\ClassMethod, $node instanceof Node\Stmt\Property, $node instanceof Node\Stmt\ClassConst,
            $node instanceof Node\Param, $node instanceof Node\Stmt\Class_ => $node->flags,
            $node instanceof Node\Expr\Closure, $node instanceof Node\Expr\ArrowFunction => $node->static ? Modifiers::STATIC : 0,
            default => 0,
        };
        $order = [Modifiers::PUBLIC, Modifiers::PROTECTED, Modifiers::PRIVATE, Modifiers::PUBLIC_SET, Modifiers::PROTECTED_SET,
            Modifiers::PRIVATE_SET, Modifiers::STATIC, Modifiers::ABSTRACT, Modifiers::FINAL, Modifiers::READONLY];

        return array_values(array_map(Modifiers::toString(...), array_filter($order, static fn (int $bit): bool => ($flags & $bit) !== 0)));
    }

    /** @return list<string> */
    private function flags(Node $node): array
    {
        $flags = [];
        if ($node instanceof Node\Param) {
            $node->variadic && $flags[] = 'variadic';
            $node->byRef && $flags[] = 'by-ref';
            $node->flags !== 0 && $flags[] = 'promoted';
        }
        if ($node instanceof Node\Arg) {
            $node->unpack && $flags[] = 'spread';
            $node->byRef && $flags[] = 'by-ref';
            $node->name !== null && $flags[] = 'named';
        }
        if ($node instanceof Node\ArrayItem && $node->unpack) {
            $flags[] = 'spread';
        }
        if ($node instanceof Node\NullableType) {
            $flags[] = 'nullable-sugar';
        }
        if ($node instanceof Node\Expr\Ternary && $node->if === null) {
            $flags[] = 'short-ternary';
        }
        if ($node instanceof Node\FunctionLike && $this->yields($node)) {
            $flags[] = 'generator';
        }

        return $flags;
    }

    private function yields(Node\FunctionLike $function): bool
    {
        $stack = $function->getStmts() ?? [];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node instanceof Node\Expr\Yield_ || $node instanceof Node\Expr\YieldFrom) {
                return true;
            }
            if ($node instanceof Node\FunctionLike || $node instanceof Node\Stmt\ClassLike) {
                continue;
            }
            foreach ($node->getSubNodeNames() as $name) {
                foreach (is_array($node->$name) ? $node->$name : [$node->$name] as $child) {
                    $child instanceof Node && $stack[] = $child;
                }
            }
        }

        return false;
    }

    private function type(Node $type): array
    {
        if ($type instanceof Node\NullableType) {
            $inner = $this->type($type->type);

            return ['text' => '?' . $inner['text']] + $inner + ['nullable' => true];
        }
        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            $members = array_map($this->type(...), $type->types);
            $out = [
                'text' => implode($type instanceof Node\UnionType ? '|' : '&', array_column($members, 'text')),
                'kind' => $type instanceof Node\UnionType ? 'union' : 'intersection',
                'members' => $members,
            ];
            if (array_intersect(['null', 'mixed'], array_column($members, 'name')) !== [] || array_filter(array_column($members, 'nullable')) !== []) {
                $out['nullable'] = true;
            }

            return $out + ['origin' => 'written'];
        }
        if ($type instanceof Node\Name && ! $type->isSpecialClassName()) {
            $this->referenced[$type->toLowerString()] = $type->toString();

            return ['text' => '\\' . $type->toString(), 'kind' => 'named', 'name' => $type->toString(), 'origin' => 'written'];
        }
        $keyword = $type->toString();
        $out = ['text' => $keyword, 'kind' => 'keyword', 'name' => $keyword];
        if (in_array(strtolower($keyword), ['null', 'mixed'], true)) {
            $out['nullable'] = true;
        }

        return $out + ['origin' => 'written'];
    }

    /** @param  list<Node\Name>  $types */
    private function caught(array $types): array
    {
        if (count($types) === 1) {
            return $this->type($types[0]);
        }
        $members = array_map($this->type(...), $types);

        return ['text' => implode('|', array_column($members, 'text')), 'kind' => 'union', 'members' => $members, 'origin' => 'written'];
    }

    private function symbolOf(Node $node): ?string
    {
        $symbol = match (true) {
            $node instanceof Node\Stmt\ClassLike => $node->namespacedName?->toString(),
            $node instanceof Node\Stmt\Function_ => $node->namespacedName->toString() . '()',
            $this->class === null => null,
            $node instanceof Node\Stmt\ClassMethod => "{$this->class}::{$node->name}()",
            $node instanceof Node\PropertyItem => "{$this->class}::\${$node->name}",
            $node instanceof Node\Param && $node->flags !== 0 && $node->var instanceof Node\Expr\Variable => "{$this->class}::\${$node->var->name}",
            $node instanceof Node\Const_, $node instanceof Node\Stmt\EnumCase => "{$this->class}::{$node->name}",
            default => null,
        };
        if ($node instanceof Node\Stmt\ClassLike && $symbol !== null) {
            $this->declared[strtolower($symbol)] = $symbol;
        }

        return $symbol;
    }

    /** The symbol a name or an import names, as NameResolver resolved it; a name it could not resolve names none. */
    private function refersOf(Node $node, ?Node $parent): ?string
    {
        if ($node instanceof Node\UseItem) {
            $use = $parent instanceof Node\Stmt\GroupUse ? $parent->type : ($parent instanceof Node\Stmt\Use_ ? $parent->type : Node\Stmt\Use_::TYPE_UNKNOWN);
            $type = $node->type !== Node\Stmt\Use_::TYPE_UNKNOWN ? $node->type : $use;
            $name = $parent instanceof Node\Stmt\GroupUse ? Node\Name::concat($parent->prefix, $node->name)->toString() : $node->name->toString();

            return $this->named($name, $type);
        }
        if (! $node instanceof Node\Name\FullyQualified) {
            return null;
        }

        return match (true) {
            $parent instanceof Node\Expr\FuncCall && $parent->name === $node => $this->named($node->toString(), Node\Stmt\Use_::TYPE_FUNCTION),
            $parent instanceof Node\Expr\ConstFetch => null,
            default => $this->named($node->toString(), Node\Stmt\Use_::TYPE_NORMAL),
        };
    }

    private function named(string $name, int $type): ?string
    {
        return match ($type) {
            Node\Stmt\Use_::TYPE_NORMAL => $this->referenced[strtolower($name)] = $name,
            Node\Stmt\Use_::TYPE_FUNCTION => "{$name}()",
            default => null,
        };
    }

    private function attachment(int $start, int $end): array
    {
        $lineStart = strrpos(substr($this->code, 0, $start), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        if (trim(substr($this->code, $lineStart, $start - $lineStart)) !== '') {
            $owner = $this->outermost(fn (array $span): bool => $span['id'] !== 0 && $span['end'] <= $start && $span['end'] > $lineStart);

            return $owner === null ? ['trailing' => true] : ['attached' => $owner, 'trailing' => true];
        }
        $next = strspn($this->code, " \t\r\n", $end) + $end;
        while (isset($this->commentEnds[$next])) {
            $next = strspn($this->code, " \t\r\n", $this->commentEnds[$next]) + $this->commentEnds[$next];
        }
        $owner = $this->outermost(fn (array $span): bool => $span['id'] !== 0 && $span['start'] === $next);

        return $owner === null ? [] : ['attached' => $owner];
    }

    private function outermost(Closure $matches): ?int
    {
        foreach ($this->spans as $span) {
            if ($matches($span)) {
                return $span['id'];
            }
        }

        return null;
    }
}
