<?php

/**
 * Writes PHP files as one generic tree stream (contract/CONTRACT.md), the way a PHP bridge will.
 *
 * Usage: php php.php (<file> <path-in-stream>)...
 */

declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

$pairs = array_chunk(array_slice($argv, 1), 2);
$line = static fn (array $object): string => json_encode($object, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";

echo $line(['header' => ['contract' => 'tree', 'version' => 1, 'language' => 'php', 'bridge' => ['name' => 'contract/samples/emit/php.php', 'version' => '1'], 'roots' => array_column($pairs, 1)]]);
$referenced = [];
foreach ($pairs as [$source, $shown]) {
    $code = file_get_contents($source);
    $parser = (new ParserFactory())->createForNewestSupportedVersion();
    $statements = (new NodeTraverser(new NameResolver()))->traverse($parser->parse($code));
    $writer = new TreeWriter($code);
    $root = $writer->root($statements);
    echo $line(['file' => ['path' => $shown, 'language' => 'php', 'errors' => 0, 'root' => $root, 'comments' => $writer->comments($parser->getTokens())]]);
    $referenced += $writer->referenced;
}
echo $line(['program' => ['symbols' => (new OutsideSymbols($referenced))->all()]]);
echo $line(['trailer' => ['files' => count($pairs)]]);

final class TreeWriter
{
    /** @var list<array{start: int, end: int, id: int}> */
    private array $spans = [];

    /** @var array<string, true> */
    public array $referenced = [];

    private int $next = 0;

    /** @var array<int, int> */
    private array $commentEnds = [];

    private ?string $class = null;

    public function __construct(private readonly string $code) {}

    /** @param list<Node> $statements */
    public function root(array $statements): array
    {
        $first = $statements[0];

        return $this->node($first, null);
    }

    private function node(Node $node, ?string $field, ?Node $parent = null): array
    {
        $id = $this->next++;
        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos() + 1;
        $this->spans[] = ['start' => $start, 'end' => $end, 'id' => $id];
        $out = ['id' => $id, 'kind' => $node->getType(), 'role' => $this->role($node, $field)];
        $is = $this->neutral($node);
        if ($is !== []) {
            $out['is'] = $is;
        }
        $out['span'] = [$start, $end, $node->getStartLine()];
        if ($field !== null) {
            $out['field'] = $field;
        }
        $enclosing = $this->class;
        if ($node instanceof Node\Stmt\ClassLike && $node->namespacedName !== null) {
            $this->class = $node->namespacedName->toString();
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

    private function role(Node $node, ?string $field): string
    {
        return match (true) {
            $node instanceof Node\Stmt\ClassLike, $node instanceof Node\Stmt\ClassMethod, $node instanceof Node\Stmt\Function_,
            $node instanceof Node\Stmt\Property, $node instanceof Node\Stmt\ClassConst, $node instanceof Node\Stmt\EnumCase => 'member',
            $node instanceof Node\Stmt => 'statement',
            $node instanceof Node\ComplexType, in_array($field, ['type', 'returnType'], true) => 'type',
            $node instanceof Node\Expr, $node instanceof Node\Scalar => 'expression',
            default => 'other',
        };
    }

    /** @return list<string> */
    private function neutral(Node $node): array
    {
        $is = [];
        $answers = [
            'function' => $node instanceof Node\FunctionLike && ! ($node instanceof Node\Stmt\ClassMethod && $node->stmts === null),
            'type-declaration' => $node instanceof Node\Stmt\ClassLike,
            'parameter' => $node instanceof Node\Param,
            'branch' => $node instanceof Node\Stmt\If_ || $node instanceof Node\Stmt\Switch_ || $node instanceof Node\Expr\Match_ || $node instanceof Node\Expr\Ternary,
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
            'self-reference' => $node instanceof Node\Expr\Variable && $node->name === 'this',
            'identifier' => $node instanceof Node\Expr\Variable && $node->name !== 'this',
            'assignment' => $node instanceof Node\Expr\Assign || $node instanceof Node\Expr\AssignOp || $node instanceof Node\Expr\AssignRef,
            'comparison' => $node instanceof Node\Expr\BinaryOp\Identical || $node instanceof Node\Expr\BinaryOp\NotIdentical || $node instanceof Node\Expr\BinaryOp\Equal
                || $node instanceof Node\Expr\BinaryOp\NotEqual || $node instanceof Node\Expr\BinaryOp\Smaller || $node instanceof Node\Expr\BinaryOp\Greater
                || $node instanceof Node\Expr\BinaryOp\SmallerOrEqual || $node instanceof Node\Expr\BinaryOp\GreaterOrEqual,
            'literal' => $node instanceof Node\Scalar,
            'import' => $node instanceof Node\Stmt\Use_ || $node instanceof Node\Stmt\GroupUse,
            'catch' => $node instanceof Node\Stmt\Catch_,
        ];
        foreach ($answers as $neutral => $answers_) {
            if ($answers_) {
                $is[] = $neutral;
            }
        }

        return $is;
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
        if (($node instanceof Node\Param || $node instanceof Node\Stmt\Property) && $node->type !== null) {
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
        $refers = match (true) {
            $node instanceof Node\Name\FullyQualified && ! $parent instanceof Node\Expr\FuncCall && ! $parent instanceof Node\Expr\ConstFetch => $node->toString(),
            $node instanceof Node\UseItem && $node->type === Node\Stmt\Use_::TYPE_NORMAL => $node->name->toString(),
            default => null,
        };
        if ($refers !== null) {
            $facts['refers'] = $refers;
            $this->referenced[$refers] = true;
        }

        return $facts;
    }

    private function nameOf(Node $node): ?string
    {
        return match (true) {
            $node instanceof Node\Identifier, $node instanceof Node\Name => $node->toString(),
            $node instanceof Node\Expr\Variable && is_string($node->name) => $node->name,
            $node instanceof Node\Stmt\ClassLike && $node->name !== null => $node->name->toString(),
            $node instanceof Node\Stmt\ClassMethod, $node instanceof Node\Stmt\Function_ => $node->name->toString(),
            $node instanceof Node\PropertyItem => $node->name->toString(),
            $node instanceof Node\Param && $node->var instanceof Node\Expr\Variable && is_string($node->var->name) => $node->var->name,
            default => null,
        };
    }

    private function literal(Node $node): array
    {
        return match (true) {
            $node instanceof Node\Scalar\String_ => ['literal' => 'string', 'value' => $node->value],
            $node instanceof Node\Scalar\Int_ => ['literal' => 'int', 'value' => (string) $node->value],
            $node instanceof Node\Scalar\Float_ => ['literal' => 'float', 'value' => (string) $node->value],
            $node instanceof Node\Scalar\InterpolatedString => ['literal' => 'interpolated'],
            $node instanceof Node\Expr\ConstFetch && in_array($node->name->toLowerString(), ['true', 'false'], true)
                => ['literal' => 'bool', 'value' => $node->name->toLowerString() === 'true'],
            $node instanceof Node\Expr\ConstFetch && $node->name->toLowerString() === 'null' => ['literal' => 'null', 'value' => null],
            default => [],
        };
    }

    private function operator(Node $node): ?string
    {
        return match (true) {
            $node instanceof Node\Expr\BinaryOp => $node->getOperatorSigil(),
            $node instanceof Node\Expr\Assign => '=',
            $node instanceof Node\Expr\AssignOp => [
                'Plus' => '+', 'Minus' => '-', 'Mul' => '*', 'Div' => '/', 'Concat' => '.', 'Mod' => '%', 'Pow' => '**', 'Coalesce' => '??',
                'BitwiseAnd' => '&', 'BitwiseOr' => '|', 'BitwiseXor' => '^', 'ShiftLeft' => '<<', 'ShiftRight' => '>>',
            ][substr($node->getType(), strlen('Expr_AssignOp_'))] . '=',
            $node instanceof Node\Expr\PreInc, $node instanceof Node\Expr\PostInc => '++',
            $node instanceof Node\Expr\PreDec, $node instanceof Node\Expr\PostDec => '--',
            $node instanceof Node\Expr\UnaryMinus => '-',
            $node instanceof Node\Expr\BooleanNot => '!',
            $node instanceof Node\Expr\Instanceof_ => 'instanceof',
            default => null,
        };
    }

    /** @return list<string> */
    private function modifiers(Node $node): array
    {
        $flags = match (true) {
            $node instanceof Node\Stmt\ClassMethod, $node instanceof Node\Stmt\Property, $node instanceof Node\Stmt\ClassConst,
            $node instanceof Node\Param, $node instanceof Node\Stmt\Class_ => $node->flags,
            default => 0,
        };
        $order = [Modifiers::PUBLIC, Modifiers::PROTECTED, Modifiers::PRIVATE, Modifiers::STATIC, Modifiers::ABSTRACT, Modifiers::FINAL, Modifiers::READONLY];

        return array_values(array_map(Modifiers::toString(...), array_filter($order, static fn (int $bit): bool => ($flags & $bit) !== 0)));
    }

    /** @return list<string> */
    private function flags(Node $node): array
    {
        $flags = [];
        if ($node instanceof Node\Param) {
            if ($node->variadic) {
                $flags[] = 'variadic';
            }
            if ($node->byRef) {
                $flags[] = 'by-ref';
            }
            if ($node->flags !== 0) {
                $flags[] = 'promoted';
            }
        }
        if ($node instanceof Node\Arg) {
            if ($node->unpack) {
                $flags[] = 'spread';
            }
            if ($node->name !== null) {
                $flags[] = 'named';
            }
        }
        if ($node instanceof Node\NullableType) {
            $flags[] = 'nullable-sugar';
        }
        if ($node instanceof Node\Expr\Ternary && $node->if === null) {
            $flags[] = 'short-ternary';
        }

        return $flags;
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
            if (in_array('null', array_column($members, 'name'), true)) {
                $out['nullable'] = true;
            }

            return $out + ['origin' => 'written'];
        }
        if ($type instanceof Node\Name) {
            $this->referenced[$type->toString()] = true;

            return ['text' => '\\' . $type->toString(), 'kind' => 'named', 'name' => $type->toString(), 'origin' => 'written'];
        }

        return ['text' => $type->toString(), 'kind' => 'keyword', 'name' => $type->toString(), 'origin' => 'written'];
    }

    /** @param list<Node\Name> $types */
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
        return match (true) {
            $node instanceof Node\Stmt\ClassLike && $node->namespacedName !== null => $node->namespacedName->toString(),
            $node instanceof Node\Stmt\Function_ => $node->namespacedName->toString() . '()',
            $node instanceof Node\Stmt\ClassMethod && $this->class !== null => "{$this->class}::{$node->name}()",
            $node instanceof Node\PropertyItem && $this->class !== null => "{$this->class}::\${$node->name}",
            $node instanceof Node\Param && $node->flags !== 0 && $this->class !== null => "{$this->class}::\${$node->var->name}",
            $node instanceof Node\Const_ && $this->class !== null => "{$this->class}::{$node->name}",
            default => null,
        };
    }

    /** @param list<\PhpParser\Token> $tokens */
    public function comments(array $tokens): array
    {
        foreach ($tokens as $token) {
            if (in_array($token->id, [T_COMMENT, T_DOC_COMMENT], true)) {
                $this->commentEnds[$token->pos] = $token->pos + strlen($token->text);
            }
        }
        $comments = [];
        foreach ($tokens as $token) {
            if (! in_array($token->id, [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $start = $token->pos;
            $end = $start + strlen($token->text);
            $comment = [
                'id' => count($comments),
                'kind' => $token->id === T_DOC_COMMENT ? 'doc' : (str_starts_with($token->text, '/*') ? 'block' : 'line'),
                'text' => rtrim($token->text, "\n"),
                'span' => [$start, $start + strlen(rtrim($token->text, "\n")), $token->line],
            ];
            $comments[] = $comment + $this->attachment($start, $end);
        }

        return $comments;
    }

    private function attachment(int $start, int $end): array
    {
        $lineStart = strrpos(substr($this->code, 0, $start), "\n");
        $before = trim(substr($this->code, $lineStart === false ? 0 : $lineStart + 1, $start - ($lineStart === false ? 0 : $lineStart + 1)));
        if ($before !== '') {
            $owner = $this->outermost(fn (array $span): bool => $span['end'] <= $start && $span['end'] > ($lineStart === false ? 0 : $lineStart));

            return $owner === null ? ['trailing' => true] : ['attached' => $owner, 'trailing' => true];
        }
        $next = strspn($this->code, " \t\r\n", $end) + $end;
        while (isset($this->commentEnds[$next])) {
            $next = strspn($this->code, " \t\r\n", $this->commentEnds[$next]) + $this->commentEnds[$next];
        }
        $owner = $this->outermost(fn (array $span): bool => $span['start'] === $next);

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

final class OutsideSymbols
{
    /** @var array<string, array> */
    private array $symbols = [];

    /** @param array<string, true> $referenced */
    public function __construct(array $referenced)
    {
        foreach (array_keys($referenced) as $class) {
            $this->add($class);
        }
    }

    public function all(): array
    {
        return array_values($this->symbols);
    }

    private function add(string $class): void
    {
        if (isset($this->symbols[$class]) || ! (class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class))) {
            return;
        }
        $reflection = new ReflectionClass($class);
        $symbol = ['symbol' => $reflection->getName(), 'kind' => $this->kind($reflection), 'name' => $reflection->getShortName()];
        $this->symbols[$class] = &$symbol;
        $parent = $reflection->getParentClass();
        if ($parent !== false) {
            $symbol['extends'] = [$parent->getName()];
            $this->add($parent->getName());
        }
        $interfaces = $reflection->getInterfaceNames();
        if ($interfaces !== []) {
            $symbol['implements'] = $interfaces;
            array_map($this->add(...), $interfaces);
        }
        $modifiers = array_keys(array_filter(['final' => $reflection->isFinal(), 'abstract' => $reflection->isAbstract() && ! $reflection->isInterface(), 'readonly' => $reflection->isReadOnly()]));
        if ($modifiers !== []) {
            $symbol['modifiers'] = $modifiers;
        }
        $members = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }
            $member = ['symbol' => "{$reflection->getName()}::{$method->getName()}()", 'name' => $method->getName(), 'kind' => 'method'];
            if ($method->hasReturnType()) {
                $member['returns'] = $this->type($method->getReturnType());
            }
            $parameters = [];
            foreach ($method->getParameters() as $parameter) {
                $entry = ['name' => $parameter->getName()];
                if ($parameter->hasType()) {
                    $entry['declared'] = $this->type($parameter->getType());
                }
                if ($parameter->isVariadic()) {
                    $entry['flags'] = ['variadic'];
                }
                $parameters[] = $entry;
            }
            if ($parameters !== []) {
                $member['parameters'] = $parameters;
            }
            $members[] = $member;
        }
        if ($members !== []) {
            $symbol['members'] = $members;
        }
        unset($symbol);
    }

    private function kind(ReflectionClass $reflection): string
    {
        return match (true) {
            $reflection->isInterface() => 'interface',
            $reflection->isTrait() => 'trait',
            $reflection->isEnum() => 'enum',
            default => 'class',
        };
    }

    private function type(ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            $named = ! $type->isBuiltin();
            if ($named) {
                $this->add($name);
            }
            $out = ['text' => ($type->allowsNull() && $name !== 'null' && $name !== 'mixed' ? '?' : '') . ($named ? '\\' : '') . $name, 'kind' => $named ? 'named' : 'keyword', 'name' => $name];
            if ($type->allowsNull() && $name !== 'mixed') {
                $out['nullable'] = true;
            }

            return $out + ['origin' => 'written'];
        }
        $members = array_map($this->type(...), $type->getTypes());

        return ['text' => (string) $type, 'kind' => $type instanceof ReflectionUnionType ? 'union' : 'intersection', 'members' => $members, 'origin' => 'written'];
    }
}
