<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use PhpParser\ErrorHandler\Collecting;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use PhpParser\Token;

/**
 * One parsed PHP file translated into the version 5 generic tree (contract/CONTRACT.md): one neutral kind per
 * operation, its parts under named roles, its facts, and byte spans into the file.
 */
final class Translator
{
    /**
     * Classes the file names, keyed by their lower-cased name: the ones reflection may find outside the scan.
     *
     * @var array<string, string>
     */
    public array $referenced = [];

    /**
     * Class-likes the file declares, keyed by their lower-cased name.
     *
     * @var array<string, string>
     */
    public array $declared = [];

    /**
     * The file's type table: each type an expression resolved to, once, which its resolvedType fact names by index.
     *
     * @var list<array<string, mixed>>
     */
    public array $types = [];

    /**
     * @var list<int> where each line starts
     */
    private array $lines = [0];

    /**
     * @var list<int> where each significant token starts
     */
    private array $positions = [];

    /**
     * @var list<Token> the significant tokens, in order
     */
    private array $significant = [];

    /**
     * @var array<int, int> each opening brace's offset, to the offset just past its closing brace
     */
    private array $closing = [];

    /**
     * @var list<array<string, TreeNode>> the bindings each enclosing function brings into scope, innermost last
     */
    private array $scopes = [];

    /**
     * The classes around the code being translated, innermost last, each its symbol and its parent's: what self,
     * static and parent name.
     *
     * @var list<array{self: ?string, parent: ?string}>
     */
    private array $classes = [];

    private ?string $namespace = null;

    /**
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * @var list<array{int, int, string, ?string}> each type's span from its doc comment on, its symbol and its parent's
     */
    private array $typeSpans = [];

    private const COPIED_KEYWORDS = ['array', 'string', 'int', 'float', 'bool', 'true', 'false'];

    private const COMPARISONS = [
        'Expr_BinaryOp_Identical' => 'identical', 'Expr_BinaryOp_NotIdentical' => 'not-identical', 'Expr_BinaryOp_Equal' => 'equal',
        'Expr_BinaryOp_NotEqual' => 'not-equal', 'Expr_BinaryOp_Smaller' => 'less', 'Expr_BinaryOp_SmallerOrEqual' => 'less-or-equal',
        'Expr_BinaryOp_Greater' => 'greater', 'Expr_BinaryOp_GreaterOrEqual' => 'greater-or-equal', 'Expr_BinaryOp_Spaceship' => 'compare',
    ];

    private const BINARIES = [
        'Plus' => 'add', 'Minus' => 'subtract', 'Mul' => 'multiply', 'Div' => 'divide', 'Mod' => 'modulo', 'Pow' => 'power',
        'Concat' => 'concatenate', 'BitwiseAnd' => 'bit-and', 'BitwiseOr' => 'bit-or', 'BitwiseXor' => 'bit-xor',
        'ShiftLeft' => 'shift-left', 'ShiftRight' => 'shift-right', 'LogicalXor' => 'xor',
    ];

    /**
     * @param  list<Token>  $tokens
     */
    public function __construct(private readonly string $code, private readonly array $tokens, private readonly ?Types $known = null)
    {
        preg_match_all('/\n/', $code, $breaks, PREG_OFFSET_CAPTURE);
        foreach ($breaks[0] as [, $at]) {
            $this->lines[] = $at + 1;
        }
        $open = [];
        foreach ($tokens as $token) {
            if (in_array($token->id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG], true)) {
                continue;
            }
            $this->positions[] = $token->pos;
            $this->significant[] = $token;
            if ($token->text === '{' || $token->id === T_CURLY_OPEN || $token->id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $open[] = $token->pos;
            } elseif ($token->text === '}' && $open !== []) {
                $this->closing[array_pop($open)] = $token->pos + 1;
            }
        }
    }

    public function line(int $offset): int
    {
        $low = 0;
        $high = count($this->lines) - 1;
        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);
            if ($this->lines[$middle] <= $offset) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        return $low + 1;
    }

    // Positions ------------------------------------------------------------------------------------------------------

    private function start(Node $node): int
    {
        return $node->getStartFilePos();
    }

    private function end(Node $node): int
    {
        return $node->getEndFilePos() + 1;
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function of(string $kind, Node $node, array $facts = []): TreeNode
    {
        return $this->made($kind, $this->start($node), $this->end($node), $facts);
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function made(string $kind, int $start, int $end, array $facts = []): TreeNode
    {
        return new TreeNode($kind, $start, $end, array_filter($facts, static fn (mixed $value): bool => $value !== null && $value !== false && $value !== []));
    }

    /**
     * The index of the first significant token at or after the offset.
     */
    private function tokenAt(int $offset): int
    {
        $low = 0;
        $high = count($this->positions);
        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            if ($this->positions[$middle] < $offset) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }

    /**
     * The braced block a construct's header ends at $after opens, as its span: the first token after the header's
     * closing parentheses when it is `{`; none when the body is written without braces.
     *
     * @return array{int, int}|null
     */
    private function braces(int $after): ?array
    {
        $index = $this->tokenAt($after);
        while ($this->isParenthesis($index)) {
            $index++;
        }
        $token = $this->significant[$index] ?? null;
        if ($token === null || $token->text !== '{' || ! isset($this->closing[$token->pos])) {
            return null;
        }

        return [$token->pos, $this->closing[$token->pos]];
    }

    private function isParenthesis(int $index): bool
    {
        return isset($this->significant[$index]) && in_array($this->significant[$index]->text, [')', '('], true);
    }

    /**
     * A body: the braced block after the header, else the statements as written.
     *
     * @param  list<Node>  $statements
     */
    private function body(int $after, array $statements, ?string $class): TreeNode
    {
        $held = array_map(fn (Node $statement) => $this->statement($statement, $class), $this->written($statements));
        $braced = $this->braces($after);
        if ($braced !== null) {
            $block = $this->made('block', $braced[0], $braced[1]);
        } elseif ($held !== []) {
            $block = $this->made('block', $held[0]->start, end($held)->end);
        } else {
            $block = $this->made('block', $after, $after);
        }
        $block->parts = ['statements' => $held];

        return $block;
    }

    /**
     * The statements as written: php-parser stands a Nop where a block's last comment is, which is no statement; only a
     * lone `;` is.
     *
     * @param  list<Node>  $statements
     * @return list<Node>
     */
    private function written(array $statements): array
    {
        return array_values(array_filter($statements, fn (Node $statement): bool => ! $statement instanceof Stmt\Nop
            || substr($this->code, $this->start($statement), $this->end($statement) - $this->start($statement)) === ';'));
    }

    // The file -------------------------------------------------------------------------------------------------------

    /**
     * @param  list<Node>  $statements
     */
    public function file(array $statements): TreeNode
    {
        $whole = strlen($this->code);
        $namespaces = array_values(array_filter($statements, static fn (Node $node): bool => $node instanceof Stmt\Namespace_));
        if ($namespaces === []) {
            $modules = [$this->module(null, 0, $whole, $statements)];
        } elseif (count($namespaces) === 1 && ! $this->bracedNamespace($namespaces[0])) {
            $modules = [$this->module($namespaces[0]->name?->toString(), 0, $whole, $namespaces[0]->stmts)];
        } else {
            $modules = array_map(fn (Stmt\Namespace_ $namespace) => $this->module(
                $namespace->name?->toString(), $this->start($namespace), $this->end($namespace), $namespace->stmts,
            ), $namespaces);
        }
        $root = $this->made('file', 0, $whole);
        $root->parts = ['modules' => $modules];

        return $root;
    }

    private function bracedNamespace(Stmt\Namespace_ $namespace): bool
    {
        $after = $namespace->name !== null ? $this->end($namespace->name) : $this->start($namespace) + strlen('namespace');

        return $this->braces($after) !== null;
    }

    /**
     * @param  list<Node>  $statements
     */
    private function module(?string $name, int $start, int $end, array $statements): TreeNode
    {
        $this->namespace = $name;
        $module = $this->made('module', $start, $end, ['name' => $name]);
        $roles = ['imports' => [], 'declarations' => [], 'statements' => []];
        foreach ($this->written($statements) as $statement) {
            $role = match (true) {
                $statement instanceof Stmt\Use_, $statement instanceof Stmt\GroupUse => 'imports',
                $statement instanceof Stmt\ClassLike, $statement instanceof Stmt\Function_ => 'declarations',
                default => 'statements',
            };
            if ($role === 'imports') {
                array_push($roles['imports'], ...$this->imports($statement));
            } else {
                $roles[$role][] = $this->statement($statement, null);
            }
        }
        $module->parts = array_filter($roles, static fn (array $held): bool => $held !== []);

        return $module;
    }

    // Statements -----------------------------------------------------------------------------------------------------

    private function statement(Node $statement, ?string $class): TreeNode
    {
        return match (true) {
            $statement instanceof Stmt\Expression && $statement->expr instanceof Expr\Throw_ => $this->thrown($statement, $statement->expr),
            $statement instanceof Stmt\Expression => $this->owning('expression-statement', $statement, ['expression' => $this->expression($statement->expr)]),
            $statement instanceof Stmt\Echo_ => $this->owning('expression-statement', $statement, ['expression' => $this->construct('echo', $statement, $statement->exprs)]),
            $statement instanceof Stmt\Unset_ => $this->owning('expression-statement', $statement, ['expression' => $this->construct('unset', $statement, $statement->vars)]),
            $statement instanceof Stmt\Return_ => $this->owning('return', $statement, ['values' => $statement->expr !== null ? [$this->expression($statement->expr)] : []]),
            $statement instanceof Stmt\If_ => $this->ifStatement($statement, $class),
            $statement instanceof Stmt\Foreach_ => $this->foreachLoop($statement, $class),
            $statement instanceof Stmt\For_ => $this->forLoop($statement, $class),
            $statement instanceof Stmt\While_ => $this->owning('loop', $statement, [
                'condition' => $this->expression($statement->cond),
                'body' => $this->body($this->end($statement->cond), $statement->stmts, $class),
            ], ['form' => 'while']),
            $statement instanceof Stmt\Do_ => $this->owning('loop', $statement, [
                'body' => $this->body($this->start($statement) + 2, $statement->stmts, $class),
                'condition' => $this->expression($statement->cond),
            ], ['form' => 'do-while']),
            $statement instanceof Stmt\Switch_ => $this->switchStatement($statement, $class),
            $statement instanceof Stmt\TryCatch => $this->tryStatement($statement, $class),
            $statement instanceof Stmt\Break_ => $this->of('break', $statement, ['depth' => $statement->num instanceof Node\Scalar\Int_ ? $statement->num->value : null]),
            $statement instanceof Stmt\Continue_ => $this->of('continue', $statement, ['depth' => $statement->num instanceof Node\Scalar\Int_ ? $statement->num->value : null]),
            $statement instanceof Stmt\Nop => $this->of('no-op', $statement),
            $statement instanceof Stmt\Global_ => $this->owning('scope-declaration', $statement, ['bindings' => array_map($this->binding(...), $statement->vars)], ['form' => 'global']),
            $statement instanceof Stmt\Static_ => $this->owning('variable', $statement, ['declarators' => array_map(fn (Node\StaticVar $static) => $this->declarator($static, $static->var, $static->default), $statement->vars)], ['form' => 'static']),
            $statement instanceof Stmt\Function_ => $this->function($statement, 'function', $class),
            $statement instanceof Stmt\ClassLike => $this->type($statement),
            $statement instanceof Stmt\Const_ => $this->owning('constant', $statement, ['declarators' => array_map(fn (Node\Const_ $constant) => $this->constantDeclarator($constant), $statement->consts)]),
            $statement instanceof Stmt\Label => $this->owning('label', $statement, ['binding' => $this->made('binding', $this->start($statement->name), $this->end($statement->name), ['name' => $statement->name->toString()])]),
            $statement instanceof Stmt\Goto_ => $this->owning('goto', $statement, ['destination' => $this->made('reference', $this->start($statement->name), $this->end($statement->name), ['name' => $statement->name->toString(), 'form' => 'name'])]),
            $statement instanceof Stmt\Use_, $statement instanceof Stmt\GroupUse => $this->imports($statement)[0],
            default => $this->unsupported($statement),
        };
    }

    /**
     * @param  array<string, TreeNode|list<TreeNode>>  $parts
     * @param  array<string, mixed>  $facts
     */
    private function owning(string $kind, Node $node, array $parts, array $facts = []): TreeNode
    {
        $made = $this->of($kind, $node, $facts);
        $made->parts = $parts;

        return $made;
    }

    private function unsupported(Node $node): TreeNode
    {
        $parts = array_map(fn (Expr $part) => $this->expression($part), $this->expressionsWithin($node));
        $name = strtolower(str_replace(['Stmt_', 'Expr_', '_'], ['', '', '-'], $node->getType()));

        return $this->owning('unsupported', $node, $parts !== [] ? ['parts' => $parts] : [], ['construct' => trim($name, '-')]);
    }

    /**
     * @return list<Expr>
     */
    private function expressionsWithin(Node $node): array
    {
        $found = [];
        foreach ($node->getSubNodeNames() as $name) {
            foreach (is_array($node->$name) ? $node->$name : [$node->$name] as $child) {
                if ($child instanceof Expr) {
                    $found[] = $child;
                } elseif ($child instanceof Node && !$child instanceof Stmt) {
                    array_push($found, ...$this->expressionsWithin($child));
                }
            }
        }

        return $found;
    }

    private function thrown(Node $statement, Expr\Throw_ $throw): TreeNode
    {
        return $this->owning('throw', $statement, ['value' => $this->expression($throw->expr)]);
    }

    private function ifStatement(Stmt\If_ $if, ?string $class): TreeNode
    {
        $else = null;
        if ($if->else !== null) {
            $else = $this->body($this->start($if->else) + strlen('else'), $if->else->stmts, $class);
        }
        foreach (array_reverse($if->elseifs) as $elseif) {
            $chained = $this->made('if', $this->start($elseif), $else?->end ?? $this->end($elseif));
            $chained->parts = ['condition' => $this->expression($elseif->cond), 'then' => $this->body($this->end($elseif->cond), $elseif->stmts, $class)];
            if ($else !== null) {
                $chained->parts['else'] = $else;
            }
            $else = $chained;
        }
        $made = $this->of('if', $if);
        $made->parts = ['condition' => $this->expression($if->cond), 'then' => $this->body($this->end($if->cond), $if->stmts, $class)];
        if ($else !== null) {
            $made->parts['else'] = $else;
        }

        return $made;
    }

    private function foreachLoop(Stmt\Foreach_ $loop, ?string $class): TreeNode
    {
        $bindings = array_map($this->binding(...), array_values(array_filter([$loop->keyVar, $loop->valueVar])));
        // A foreach binds its key and value in the function around it, so a read of either in the body is a read of it.
        if ($this->scopes !== []) {
            foreach ($bindings as $binding) {
                if (isset($binding->facts['name'])) {
                    $this->scopes[array_key_last($this->scopes)][$binding->facts['name']] ??= $binding;
                }
            }
        }

        return $this->owning('loop', $loop, [
            'iterable' => $this->expression($loop->expr),
            'bindings' => $bindings,
            'body' => $this->body($this->end($loop->valueVar), $loop->stmts, $class),
        ], ['form' => 'for-each']);
    }

    private function forLoop(Stmt\For_ $loop, ?string $class): TreeNode
    {
        $header = [...$loop->init, ...$loop->cond, ...$loop->loop];
        $after = $header === [] ? $this->start($loop) + 3 : max(array_map($this->end(...), $header));
        $made = $this->owning('loop', $loop, [
            'initializer' => array_map($this->expression(...), $loop->init),
            'steps' => array_map($this->expression(...), $loop->loop),
            'body' => $this->body($after, $loop->stmts, $class),
        ], ['form' => 'for']);
        if ($loop->cond !== []) {
            $made->parts['condition'] = $this->expression(end($loop->cond));
        }

        return $made;
    }

    private function switchStatement(Stmt\Switch_ $switch, ?string $class): TreeNode
    {
        $arms = [];
        foreach ($switch->cases as $case) {
            $statements = array_map(fn (Node $statement) => $this->statement($statement, $class), $this->written($case->stmts));
            $body = $statements === [] ? $this->made('block', $this->end($case), $this->end($case)) : $this->made('block', $statements[0]->start, end($statements)->end);
            $body->parts = ['statements' => $statements];
            $last = end($case->stmts);
            $exits = $last instanceof Stmt\Break_ || $last instanceof Stmt\Return_ || $last instanceof Stmt\Continue_
                || ($last instanceof Stmt\Expression && $last->expr instanceof Expr\Throw_);
            $arm = $this->owning('arm', $case, [
                'patterns' => $case->cond !== null ? [$this->owning('pattern', $case->cond, ['value' => $this->expression($case->cond)], ['form' => 'value'])] : [],
                'body' => $body,
            ], ['default' => $case->cond === null, 'fallsThrough' => ! $exits && $case !== end($switch->cases)]);
            $arms[] = $arm;
        }

        return $this->owning('dispatch', $switch, ['subject' => $this->expression($switch->cond), 'arms' => $arms], ['form' => 'switch']);
    }

    private function tryStatement(Stmt\TryCatch $try, ?string $class): TreeNode
    {
        $made = $this->owning('try', $try, [
            'body' => $this->body($this->start($try) + 3, $try->stmts, $class),
            'handlers' => array_map(fn (Stmt\Catch_ $catch) => $this->handler($catch, $class), $try->catches),
        ]);
        if ($try->finally !== null) {
            $made->parts['finally'] = $this->body($this->start($try->finally) + strlen('finally'), $try->finally->stmts, $class);
        }

        return $made;
    }

    private function handler(Stmt\Catch_ $catch, ?string $class): TreeNode
    {
        $after = $catch->var !== null ? $this->end($catch->var) : $this->end(end($catch->types));
        $made = $this->owning('handler', $catch, ['caughtTypes' => array_map($this->writtenType(...), $catch->types)], ['catchForm' => 'typed']);
        if ($catch->var !== null) {
            $made->parts['binding'] = $this->binding($catch->var);
        }
        $made->parts['body'] = $this->body($after, $catch->stmts, $class);

        return $made;
    }

    // Declarations ---------------------------------------------------------------------------------------------------

    private function type(Stmt\ClassLike $type): TreeNode
    {
        $symbol = $type->namespacedName?->toString();
        if ($symbol !== null) {
            $this->declared[strtolower($symbol)] = $symbol;
        }
        $form = match (true) {
            $type instanceof Stmt\Interface_ => 'interface',
            $type instanceof Stmt\Trait_ => 'trait',
            $type instanceof Stmt\Enum_ => 'enum',
            default => 'class',
        };
        $made = $this->of('type', $type, ['name' => $type->name?->toString(), 'symbol' => $symbol, 'form' => $form, 'modifiers' => $this->modifiers($type)]);
        if ($symbol !== null) {
            $this->typeSpans[] = [$type->getDocComment()?->getStartFilePos() ?? $this->start($type), $this->end($type), $symbol, $type instanceof Stmt\Class_ ? $type->extends?->toString() : null];
        }
        $parts = [];
        $extends = match (true) {
            $type instanceof Stmt\Class_ => $type->extends !== null ? [$type->extends] : [],
            $type instanceof Stmt\Interface_ => $type->extends,
            $type instanceof Stmt\Enum_ && $type->scalarType !== null => [$type->scalarType],
            default => [],
        };
        $implements = $type instanceof Stmt\Class_ || $type instanceof Stmt\Enum_ ? $type->implements : [];
        $parts['bases'] = array_map($this->writtenType(...), $extends);
        $parts['interfaces'] = array_map($this->writtenType(...), $implements);
        $parts['mixins'] = [];
        $parts['attributes'] = $this->attributes($type->attrGroups);
        $members = [];
        $promoted = [];
        $this->classes[] = ['self' => $symbol, 'parent' => $type instanceof Stmt\Class_ ? $type->extends?->toString() : null];
        foreach ($type->stmts as $member) {
            if ($member instanceof Stmt\TraitUse) {
                array_push($parts['mixins'], ...array_map($this->writtenType(...), $member->traits));

                continue;
            }
            $translated = $this->member($member, $symbol, $promoted);
            $members[] = $translated;
            if ($member instanceof Stmt\Property && $member->hooks !== []) {
                $hooks = $this->hooks($member, $symbol);
                $translated->links = ['hooks' => $hooks];
                array_push($members, ...$hooks);
            }
        }
        array_pop($this->classes);
        $parts['writtenMembers'] = $members;
        $made->parts = array_filter($parts, static fn (array $held): bool => $held !== []);
        if ($promoted !== []) {
            $made->links = ['implicitMembers' => $promoted];
        }

        return $made;
    }

    /**
     * A property's hooks, each a function of form get or set named for the property, which the field links in hooks.
     *
     * @return list<TreeNode>
     */
    private function hooks(Stmt\Property $property, ?string $class): array
    {
        $hooks = [];
        foreach ($property->hooks as $hook) {
            $made = $this->of('function', $hook, ['name' => $property->props[0]->name->toString(), 'form' => $hook->name->toLowerString() === 'set' ? 'set' : 'get']);
            $parts = ['parameters' => array_map($this->parameter(...), $hook->params), 'attributes' => $this->attributes($hook->attrGroups)];
            if ($hook->body instanceof Expr) {
                $parts['body'] = $this->expression($hook->body);
            } elseif (is_array($hook->body)) {
                $parts['body'] = $this->body($this->start($hook), $hook->body, $class);
            }
            $made->parts = array_filter($parts, static fn (mixed $held): bool => $held !== []);
            $hooks[] = $made;
        }

        return $hooks;
    }

    /**
     * @param  list<TreeNode>  $promoted  where a constructor's promoted parameters are gathered
     */
    private function member(Node $member, ?string $class, array &$promoted): TreeNode
    {
        if ($member instanceof Stmt\ClassMethod) {
            $made = $this->function($member, $member->name->toLowerString() === '__construct' ? 'constructor' : 'method', $class);
            foreach ($made->parts['parameters'] ?? [] as $index => $parameter) {
                if ($member->params[$index]->flags !== 0) {
                    $this->declares($parameter->parts['binding'], $class);
                    $promoted[] = $parameter;
                }
            }

            return $made;
        }
        if ($member instanceof Stmt\Property) {
            $made = $this->of('field', $member, ['modifiers' => $this->modifiers($member)]);
            $made->parts = array_filter([
                'attributes' => $this->attributes($member->attrGroups),
                'declarators' => array_map(fn (Node\PropertyItem $item) => $this->declarator($item, $item, $item->default), $member->props),
            ]);
            foreach ($made->parts['declarators'] as $declarator) {
                $this->declares($declarator->parts['binding'], $class);
            }
            if ($member->type !== null) {
                $made->parts['declaredType'] = $this->writtenType($member->type);
                $this->documentedVar($member, $made->parts['declaredType']);
            }

            return $made;
        }
        if ($member instanceof Stmt\ClassConst) {
            $made = $this->of('constant', $member, ['modifiers' => $this->modifiers($member)]);
            $made->parts = ['declarators' => array_map($this->constantDeclarator(...), $member->consts), 'attributes' => $this->attributes($member->attrGroups)];
            foreach ($made->parts['declarators'] as $declarator) {
                if ($class !== null) {
                    $declarator->parts['binding']->facts['symbol'] = "{$class}::{$declarator->parts['binding']->facts['name']}";
                }
            }
            if ($member->type !== null) {
                $made->parts['declaredType'] = $this->writtenType($member->type);
            }

            return $made;
        }
        if ($member instanceof Stmt\EnumCase) {
            $made = $this->of('enum-case', $member, ['name' => $member->name->toString(), 'symbol' => $class !== null ? "{$class}::{$member->name}" : null]);
            $made->parts['attributes'] = $this->attributes($member->attrGroups);
            if ($member->expr !== null) {
                $made->parts['value'] = $this->expression($member->expr);
            }

            return $made;
        }

        return $this->statement($member, $class);
    }

    /**
     * A property's binding declares the property's symbol, Class::$name, which a fetch of it refers to.
     */
    private function declares(TreeNode $binding, ?string $class): void
    {
        if ($class !== null && isset($binding->facts['name'])) {
            $binding->facts['symbol'] = "{$class}::\${$binding->facts['name']}";
        }
    }

    private function declarator(Node $declared, Node $named, ?Expr $initializer): TreeNode
    {
        $made = $this->of('declarator', $declared);
        $name = $named instanceof Expr\Variable ? $named->name : $named->name->toString();
        $start = $this->start($declared);
        $made->parts = ['binding' => $this->made('binding', $start, $start + strlen('$' . $name), ['name' => $name])];
        if ($initializer !== null) {
            $made->parts['initializer'] = $this->expression($initializer);
        }

        return $made;
    }

    private function constantDeclarator(Node\Const_ $constant): TreeNode
    {
        $made = $this->of('declarator', $constant);
        $made->parts = [
            'binding' => $this->made('binding', $this->start($constant->name), $this->end($constant->name), ['name' => $constant->name->toString()]),
            'initializer' => $this->expression($constant->value),
        ];

        return $made;
    }

    private function function(Node\FunctionLike $function, string $form, ?string $class): TreeNode
    {
        $symbol = match (true) {
            $function instanceof Stmt\Function_ => $function->namespacedName?->toString(),
            $function instanceof Stmt\ClassMethod && $class !== null => "{$class}::{$function->name}",
            default => null,
        };
        $named = $function instanceof Stmt\Function_ || $function instanceof Stmt\ClassMethod ? $function->name->toString() : null;
        $made = $this->of('function', $function, [
            'name' => $named, 'symbol' => $symbol, 'form' => $form,
            'modifiers' => $this->modifiers($function), 'generator' => $this->yields($function),
            'obligations' => $function->getAttribute('obligations'),
        ]);
        $parameters = array_map($this->parameter(...), $function->getParams());
        $parts = ['attributes' => $this->attributes($function->getAttrGroups()), 'parameters' => $parameters];
        $after = $named !== null ? $this->end($function->name) : $this->start($function) + strlen('function');
        if ($function instanceof Expr\Closure) {
            $parts['captures'] = array_map(fn (Node\ClosureUse $use) => $this->binding($use->var), $function->uses);
            foreach ($function->uses as $use) {
                $after = max($after, $this->end($use));
            }
        }
        foreach ($function->getParams() as $parameter) {
            $after = max($after, $this->end($parameter));
        }
        if ($function->getReturnType() !== null) {
            $parts['returnType'] = $this->writtenType($function->getReturnType());
            $this->documentedReturn($function, $parts['returnType']);
            $after = $this->end($function->getReturnType());
        }
        $this->scopes[] = $this->scopeOf($function, [...($parts['captures'] ?? []), ...array_map(static fn (TreeNode $parameter): TreeNode => $parameter->parts['binding'], $parameters)]);
        if ($function instanceof Expr\ArrowFunction) {
            $parts['body'] = $this->expression($function->expr);
        } elseif ($function->getStmts() !== null) {
            $parts['body'] = $this->body($after, $function->getStmts(), $class);
        }
        array_pop($this->scopes);
        $made->parts = array_filter($parts, static fn (TreeNode|array $held): bool => $held !== []);

        return $made;
    }

    /**
     * The names a function's body sees bound: its own parameters and captures, over the enclosing scope for an arrow
     * function, which reads that scope by value.
     *
     * @param  list<TreeNode>  $bindings
     * @return array<string, TreeNode>
     */
    private function scopeOf(Node\FunctionLike $function, array $bindings): array
    {
        $scope = $function instanceof Expr\ArrowFunction ? ($this->scopes[array_key_last($this->scopes)] ?? []) : [];
        foreach ($bindings as $binding) {
            if (isset($binding->facts['name'])) {
                $scope[$binding->facts['name']] = $binding;
            }
        }

        return $scope;
    }

    private function parameter(Node\Param $parameter): TreeNode
    {
        $made = $this->of('parameter', $parameter, [
            'passing' => $parameter->byRef ? 'reference' : null,
            'collects' => $parameter->variadic ? 'rest' : null,
            'modifiers' => $this->modifiers($parameter),
        ]);
        $parts = [];
        if ($parameter->type !== null) {
            $parts['declaredType'] = $this->writtenType($parameter->type);
        }
        $parts['binding'] = $this->binding($parameter->var);
        if ($parameter->default !== null) {
            $parts['default'] = $this->expression($parameter->default);
        }
        $attributes = $this->attributes($parameter->attrGroups);
        if ($attributes !== []) {
            $parts['attributes'] = $attributes;
        }
        $made->parts = $parts;

        return $made;
    }

    /**
     * @param  list<Node\AttributeGroup>  $groups
     * @return list<TreeNode>
     */
    private function attributes(array $groups): array
    {
        $found = [];
        foreach ($groups as $group) {
            foreach ($group->attrs as $attribute) {
                $made = $this->of('annotation', $attribute, ['name' => $attribute->name->toString(), 'refers' => $this->refers($attribute->name)]);
                $made->parts = ['callee' => $this->name($attribute->name), 'arguments' => array_map($this->argument(...), $attribute->args)];
                $found[] = $made;
            }
        }

        return $found;
    }

    /**
     * @return list<TreeNode>
     */
    private function imports(Stmt\Use_|Stmt\GroupUse $use): array
    {
        $prefix = $use instanceof Stmt\GroupUse ? $use->prefix->toString() . '\\' : '';
        $made = $this->of('import', $use, ['source' => $use instanceof Stmt\GroupUse ? $use->prefix->toString() : null, 'form' => 'names']);
        $bindings = [];
        foreach ($use->uses as $item) {
            $name = $prefix . $item->name->toString();
            $kind = $item->type !== Stmt\Use_::TYPE_UNKNOWN ? $item->type : $use->type;
            $this->aliases[strtolower($item->alias?->toString() ?? $item->name->getLast())] = $name;
            if ($kind === Stmt\Use_::TYPE_NORMAL) {
                $this->referenced[strtolower($name)] = $name;
            }
            $binding = $this->of('import-binding', $item, ['name' => $name, 'refers' => $kind === Stmt\Use_::TYPE_CONSTANT ? null : $name]);
            if ($item->alias !== null) {
                $binding->parts = ['alias' => $this->made('binding', $this->start($item->alias), $this->end($item->alias), ['name' => $item->alias->toString()])];
            }
            $bindings[] = $binding;
        }
        if (! $use instanceof Stmt\GroupUse && count($bindings) === 1) {
            $made->facts['source'] = $bindings[0]->facts['name'];
            if (is_string($file = $use->uses[0]->getAttribute('resolves'))) {
                $made->facts['resolves'] = $file;
            }
        }
        $made->parts = ['bindings' => $bindings];

        return [$made];
    }

    /**
     * @return list<string>
     */
    private function modifiers(Node $node): array
    {
        $flags = match (true) {
            $node instanceof Stmt\ClassMethod, $node instanceof Stmt\Property, $node instanceof Stmt\ClassConst,
            $node instanceof Node\Param, $node instanceof Stmt\Class_ => $node->flags,
            $node instanceof Expr\Closure, $node instanceof Expr\ArrowFunction => $node->static ? Modifiers::STATIC : 0,
            default => 0,
        };
        $order = [Modifiers::PUBLIC, Modifiers::PROTECTED, Modifiers::PRIVATE, Modifiers::PUBLIC_SET, Modifiers::PROTECTED_SET,
            Modifiers::PRIVATE_SET, Modifiers::STATIC, Modifiers::ABSTRACT, Modifiers::FINAL, Modifiers::READONLY];

        return array_values(array_map(Modifiers::toString(...), array_filter($order, static fn (int $bit): bool => ($flags & $bit) !== 0)));
    }

    private function yields(Node\FunctionLike $function): bool
    {
        $stack = $function->getStmts() ?? [];
        while ($stack !== []) {
            $node = array_pop($stack);
            if ($node instanceof Expr\Yield_ || $node instanceof Expr\YieldFrom) {
                return true;
            }
            if ($node instanceof Node\FunctionLike || $node instanceof Stmt\ClassLike) {
                continue;
            }
            foreach ($node->getSubNodeNames() as $name) {
                foreach (is_array($node->$name) ? $node->$name : [$node->$name] as $child) {
                    if ($child instanceof Node) {
                        $stack[] = $child;
                    }
                }
            }
        }

        return false;
    }

    // Types as written -----------------------------------------------------------------------------------------------

    private function writtenType(Node $type): TreeNode
    {
        if ($type instanceof Node\NullableType) {
            return $this->owning('type-expression', $type, ['element' => $this->writtenType($type->type)], ['form' => 'nullable']);
        }
        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            return $this->owning('type-expression', $type, ['members' => array_map($this->writtenType(...), $type->types)],
                ['form' => $type instanceof Node\UnionType ? 'union' : 'intersection']);
        }
        if ($type instanceof Node\Name && ! $type->isSpecialClassName()) {
            $made = $this->of('type-expression', $type, ['form' => 'named', 'name' => ($type->getAttribute('originalName') ?? $type)->toString(), 'refers' => $this->refers($type)]);
            if (isset($made->facts['refers'])) {
                $made->facts['resolvedType'] = $this->typeIndex(['name' => $made->facts['refers']]);
            }

            return $made;
        }

        return $this->of('type-expression', $type, ['form' => 'keyword', 'name' => $type->toString(),
            'refers' => $type instanceof Node\Name ? $this->namedClass($type) : null]);
    }

    /**
     * The class `self`, `static` or `parent` names where it is written: the enclosing class, or the one it extends.
     */
    private function namedClass(Node\Name $name): ?string
    {
        $around = $this->classes === [] ? null : $this->classes[array_key_last($this->classes)];

        return match ($name->toLowerString()) {
            'self', 'static' => $around['self'] ?? null,
            'parent' => $around['parent'] ?? null,
            default => null,
        };
    }

    private function refers(Node\Name $name): ?string
    {
        if ($name->isSpecialClassName()) {
            return null;
        }
        $resolved = $name->getAttribute('resolvedName') ?? $name;
        if (! $resolved instanceof Node\Name\FullyQualified && ! $name instanceof Node\Name\FullyQualified) {
            return null;
        }
        $this->referenced[$resolved->toLowerString()] = $resolved->toString();

        return $resolved->toString();
    }

    // Expressions ----------------------------------------------------------------------------------------------------

    /**
     * An expression, with the type the inference gave it and the declaration it binds to, when it gave them.
     */
    private function expression(Expr $expression): TreeNode
    {
        $node = $this->expressionNode($expression);
        $type = $expression->getAttribute('type');
        if (is_array($type)) {
            $node->facts['resolvedType'] = $this->typeIndex($type);
        }
        if (is_array($folded = $expression->getAttribute('constantValue'))) {
            $node->facts['constantValue'] = $folded;
        }
        $target = $expression->getAttribute('target');
        if (is_array($target) && in_array($node->kind, ['call', 'construction', 'collection', 'literal'], true)) {
            $node->facts['target'] = $target;
            $callee = $node->parts['callee'] ?? null;
            if ($expression instanceof Expr\FuncCall && $callee instanceof TreeNode && $callee->kind === 'reference' && ! isset($callee->facts['refers'])) {
                $callee->facts['refers'] = $target['symbol'];
            }
            $member = $callee instanceof TreeNode && $callee->kind === 'access' ? ($callee->parts['member'] ?? null) : null;
            if ($member instanceof TreeNode && $member->kind === 'reference' && ! isset($member->facts['refers'])) {
                $member->facts['refers'] = $target['symbol'];
            }
        }

        return $node;
    }

    /**
     * The index of a type in the file's type table, as the contract writes it: named for its class, a keyword
     * otherwise.
     *
     * @param  array{name?: string, keyword?: string, nullable?: bool}  $type
     */
    private function typeIndex(array $type): int
    {
        if (isset($type['name'])) {
            $this->referenced[strtolower($type['name'])] = $type['name'];
        }
        $written = array_filter([
            'text' => ($type['nullable'] ?? false ? '?' : '').($type['name'] ?? $type['keyword'] ?? ''),
            'kind' => isset($type['name']) ? 'named' : 'keyword',
            'name' => $type['name'] ?? $type['keyword'] ?? null,
            'nullable' => (bool) ($type['nullable'] ?? false),
            'origin' => 'compiler',
            'valueType' => in_array($type['keyword'] ?? null, self::COPIED_KEYWORDS, true) ? true : null,
        ], static fn (mixed $value): bool => $value !== null);
        $at = array_search($written, $this->types, true);
        if ($at === false) {
            $this->types[] = $written;
            $at = array_key_last($this->types);
        }

        return $at;
    }

    private function expressionNode(Expr $expression): TreeNode
    {
        return match (true) {
            $expression instanceof Expr\Variable => $this->variable($expression),
            $expression instanceof Node\Scalar\String_ => $this->of('literal', $expression, ['literalKind' => 'string']) ->with('value', $expression->value),
            $expression instanceof Node\Scalar\Int_ => $this->of('literal', $expression, ['literalKind' => 'integer', 'value' => (string) $expression->value]),
            $expression instanceof Node\Scalar\Float_ => $this->of('literal', $expression, ['literalKind' => 'float', 'value' => $this->decimal($expression->value)]),
            $expression instanceof Node\Scalar\InterpolatedString => $this->owning('interpolation', $expression, ['parts' => array_map(
                fn (Node $part): TreeNode => $part instanceof Node\InterpolatedStringPart ? $this->of('text', $part)->with('value', $part->value) : $this->expression($part),
                $expression->parts,
            )]),
            $expression instanceof Node\Scalar\MagicConst => $this->of('reference', $expression, ['name' => $expression->getName(), 'form' => 'name']),
            $expression instanceof Expr\ConstFetch => $this->constant($expression),
            $expression instanceof Expr\Assign => $this->owning('assignment', $expression, ['targets' => [$this->target($expression->var, true)], 'values' => [$this->expression($expression->expr)]], ['mode' => 'assign']),
            $expression instanceof Expr\AssignRef => $this->owning('assignment', $expression, ['targets' => [$this->target($expression->var, true)], 'values' => [$this->expression($expression->expr)]], ['mode' => 'assign']),
            $expression instanceof Expr\AssignOp\Coalesce => $this->owning('assignment', $expression, ['targets' => [$this->target($expression->var)], 'values' => [$this->expression($expression->expr)]], ['mode' => 'fallback']),
            $expression instanceof Expr\AssignOp => $this->owning('assignment', $expression, ['targets' => [$this->target($expression->var)], 'values' => [$this->expression($expression->expr)]],
                ['mode' => 'compound', 'operator' => self::BINARIES[substr($expression->getType(), strlen('Expr_AssignOp_'))] ?? null]),
            $expression instanceof Expr\PreInc, $expression instanceof Expr\PostInc => $this->owning('assignment', $expression, ['targets' => [$this->target($expression->var)]], ['mode' => 'increment', 'prefix' => $expression instanceof Expr\PreInc]),
            $expression instanceof Expr\PreDec, $expression instanceof Expr\PostDec => $this->owning('assignment', $expression, ['targets' => [$this->target($expression->var)]], ['mode' => 'decrement', 'prefix' => $expression instanceof Expr\PreDec]),
            $expression instanceof Expr\BinaryOp\Coalesce => $this->owning('fallback', $expression, ['subject' => $this->expression($expression->left), 'replacement' => $this->expression($expression->right)], ['trigger' => 'null']),
            $expression instanceof Expr\BinaryOp\BooleanAnd, $expression instanceof Expr\BinaryOp\LogicalAnd => $this->owning('logical', $expression, ['left' => $this->expression($expression->left), 'right' => $this->expression($expression->right)], ['operator' => 'and']),
            $expression instanceof Expr\BinaryOp\BooleanOr, $expression instanceof Expr\BinaryOp\LogicalOr => $this->owning('logical', $expression, ['left' => $this->expression($expression->left), 'right' => $this->expression($expression->right)], ['operator' => 'or']),
            $expression instanceof Expr\BinaryOp && isset(self::COMPARISONS[$expression->getType()]) => $this->comparison($expression),
            $expression instanceof Expr\BinaryOp && $expression->getType() === 'Expr_BinaryOp_Pipe' => $this->pipe($expression),
            $expression instanceof Expr\BinaryOp => $this->owning('binary', $expression, ['left' => $this->expression($expression->left), 'right' => $this->expression($expression->right)],
                ['operator' => self::BINARIES[substr($expression->getType(), strlen('Expr_BinaryOp_'))] ?? null]),
            $expression instanceof Expr\BooleanNot => $this->owning('unary', $expression, ['operand' => $this->expression($expression->expr)], ['operator' => 'not']),
            $expression instanceof Expr\UnaryMinus => $this->owning('unary', $expression, ['operand' => $this->expression($expression->expr)], ['operator' => 'negate']),
            $expression instanceof Expr\UnaryPlus => $this->owning('unary', $expression, ['operand' => $this->expression($expression->expr)], ['operator' => 'plus']),
            $expression instanceof Expr\BitwiseNot => $this->owning('unary', $expression, ['operand' => $this->expression($expression->expr)], ['operator' => 'bit-not']),
            $expression instanceof Expr\Ternary && $expression->if === null => $this->owning('fallback', $expression, ['subject' => $this->expression($expression->cond), 'replacement' => $this->expression($expression->else)], ['trigger' => 'falsy']),
            $expression instanceof Expr\Ternary => $this->owning('conditional', $expression, ['condition' => $this->expression($expression->cond), 'consequent' => $this->expression($expression->if), 'alternative' => $this->expression($expression->else)]),
            $expression instanceof Expr\CallLike && ! $expression instanceof Expr\New_ && $expression->isFirstClassCallable() => $this->callableValue($expression),
            $expression instanceof Expr\FuncCall => $this->owning('call', $expression, [
                'callee' => $expression->name instanceof Node\Name ? $this->name($expression->name) : $this->expression($expression->name),
                'arguments' => $this->arguments($expression),
            ]),
            $expression instanceof Expr\MethodCall, $expression instanceof Expr\NullsafeMethodCall => $this->owning('call', $expression, [
                'callee' => $this->access($expression, $expression->var, $expression->name, 'instance', $expression instanceof Expr\NullsafeMethodCall),
                'arguments' => $this->arguments($expression),
            ]),
            $expression instanceof Expr\StaticCall => $this->owning('call', $expression, [
                'callee' => $this->access($expression, $expression->class, $expression->name, 'static', false),
                'arguments' => $this->arguments($expression),
            ]),
            $expression instanceof Expr\New_ => $this->construction($expression),
            $expression instanceof Expr\PropertyFetch, $expression instanceof Expr\NullsafePropertyFetch => $this->access($expression, $expression->var, $expression->name, 'instance', $expression instanceof Expr\NullsafePropertyFetch),
            $expression instanceof Expr\StaticPropertyFetch => $this->access($expression, $expression->class, $expression->name, 'static', false),
            $expression instanceof Expr\ClassConstFetch => $this->access($expression, $expression->class, $expression->name, 'static', false),
            $expression instanceof Expr\ArrayDimFetch => $this->owning('index', $expression, array_filter(['subject' => $this->expression($expression->var), 'key' => $expression->dim !== null ? $this->expression($expression->dim) : null])),
            $expression instanceof Expr\Array_ || $expression instanceof Expr\List_ => $this->owning('collection', $expression, ['entries' => array_map($this->entry(...), array_values(array_filter($expression->items)))], ['form' => 'array']),
            $expression instanceof Expr\Closure => $this->function($expression, 'closure', null),
            $expression instanceof Expr\ArrowFunction => $this->function($expression, 'arrow', null),
            $expression instanceof Expr\Throw_ => $this->owning('throw', $expression, ['value' => $this->expression($expression->expr)]),
            $expression instanceof Expr\Yield_ => $this->owning('yield', $expression, array_filter(['value' => $expression->value !== null ? $this->expression($expression->value) : null])),
            $expression instanceof Expr\YieldFrom => $this->owning('yield', $expression, ['value' => $this->expression($expression->expr)], ['delegates' => true]),
            $expression instanceof Expr\Instanceof_ => $this->owning('type-test', $expression, [
                'subject' => $this->expression($expression->expr),
                'testedType' => $expression->class instanceof Node\Name ? $this->writtenType($expression->class) : $this->expression($expression->class),
            ], ['form' => 'instanceof']),
            $expression instanceof Expr\Cast => $this->cast($expression),
            $expression instanceof Expr\Match_ => $this->matchExpression($expression),
            $expression instanceof Expr\Isset_ => $this->construct('isset', $expression, $expression->vars, 'presence'),
            $expression instanceof Expr\Empty_ => $this->construct('empty', $expression, [$expression->expr], 'presence'),
            $expression instanceof Expr\Exit_ => $this->construct('exit', $expression, $expression->expr !== null ? [$expression->expr] : []),
            $expression instanceof Expr\Print_ => $this->construct('print', $expression, [$expression->expr]),
            $expression instanceof Expr\Clone_ => $this->construct('clone', $expression, [$expression->expr]),
            $expression instanceof Expr\Eval_ => $this->construct('eval', $expression, [$expression->expr]),
            $expression instanceof Expr\Include_ => $this->construct('include', $expression, [$expression->expr]),
            default => $this->unsupported($expression),
        };
    }

    /**
     * A variable read or written: a reference, linked to the binding of its name in the enclosing function's scope.
     */
    private function variable(Expr\Variable $variable): TreeNode
    {
        $made = $this->of('reference', $variable, [
            'name' => is_string($variable->name) ? $variable->name : null,
            'form' => $variable->name === 'this' ? 'self' : 'name',
        ]);
        $bound = is_string($variable->name) ? ($this->scopes[array_key_last($this->scopes) ?? -1][$variable->name] ?? null) : null;
        if ($bound !== null) {
            $made->links = ['binding' => [$bound]];
        }

        return $made;
    }

    /**
     * A first-class callable (`f(...)`, `$x->f(...)`, `X::f(...)`): no call, but the function itself as a value, written
     * as the expression naming it, as every language writes a function value, referring to the function it names.
     */
    private function callableValue(Expr\FuncCall|Expr\MethodCall|Expr\NullsafeMethodCall|Expr\StaticCall $callable): TreeNode
    {
        $made = match (true) {
            $callable instanceof Expr\FuncCall => $callable->name instanceof Node\Name ? $this->name($callable->name) : $this->expression($callable->name),
            $callable instanceof Expr\StaticCall => $this->access($callable, $callable->class, $callable->name, 'static', false),
            default => $this->access($callable, $callable->var, $callable->name, 'instance', $callable instanceof Expr\NullsafeMethodCall),
        };
        $symbol = $callable->getAttribute('target')['symbol'] ?? null;
        $named = $made->kind === 'access' ? ($made->parts['member'] ?? null) : $made;
        if ($symbol !== null && $named instanceof TreeNode && $named->kind === 'reference') {
            $named->facts['refers'] = $symbol;
        }

        return $made;
    }

    private function comparison(Expr\BinaryOp $comparison): TreeNode
    {
        $operator = self::COMPARISONS[$comparison->getType()];
        $left = $this->expression($comparison->left);
        $right = $this->expression($comparison->right);
        $made = $this->owning('comparison', $comparison, ['operands' => [$left, $right]], ['operators' => [$operator], 'operator' => $operator]);
        $made->links = ['left' => $left, 'right' => $right];

        return $made;
    }

    private function constant(Expr\ConstFetch $fetch): TreeNode
    {
        $name = $fetch->name->toLowerString();

        return match ($name) {
            'true', 'false' => $this->of('literal', $fetch, ['literalKind' => 'boolean'])->with('value', $name === 'true'),
            'null' => $this->of('literal', $fetch, ['literalKind' => 'null']),
            default => $this->of('reference', $fetch, ['name' => $fetch->name->toString(), 'form' => 'name', 'refers' => ltrim($fetch->name->toString(), '\\')]),
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

    private function target(Expr $target, bool $declares = false): TreeNode
    {
        $scope = array_key_last($this->scopes);
        if (! $declares || $scope === null || ! $target instanceof Expr\Variable || ! is_string($target->name) || $target->name === 'this'
            || isset($this->scopes[$scope][$target->name])) {
            return $this->expression($target);
        }
        $made = $this->binding($target);
        $this->scopes[$scope][$target->name] = $made;

        return $made;
    }

    private function binding(Node $variable): TreeNode
    {
        $name = $variable instanceof Expr\Variable && is_string($variable->name) ? $variable->name : null;

        $made = $this->of('binding', $variable, ['name' => $name]);
        $type = $variable->getAttribute('type');
        if (is_array($type)) {
            $made->facts['resolvedType'] = $this->typeIndex($type);
        }

        return $made;
    }

    /**
     * A name written as an expression: a function's callee, an attribute's, a class a static member is read from.
     */
    private function name(Node\Name $name): TreeNode
    {
        $form = match ($name->toLowerString()) {
            'self', 'static' => 'self',
            'parent' => 'parent',
            default => 'name',
        };
        $written = $name->getAttribute('originalName') ?? $name;

        $refers = $form === 'name' ? $this->refers($name) : $this->namedClass($name);

        return $this->of('reference', $name, ['name' => $written->toString(), 'form' => $form, 'refers' => $refers]);
    }

    private function access(Expr $access, Node $subject, Node $member, string $form, bool $nullConditional): TreeNode
    {
        $made = $this->made('access', $this->start($access), $this->end($member), ['form' => $form, 'nullConditional' => $nullConditional]);
        $made->parts = [
            'subject' => $subject instanceof Node\Name ? $this->name($subject) : $this->expression($subject),
            'member' => $member instanceof Expr
                ? $this->expression($member)
                : $this->of('reference', $member, ['name' => $member->toString(), 'form' => 'name', 'refers' => $access->getAttribute('member')]),
        ];

        return $made;
    }

    private function construction(Expr\New_ $new): TreeNode
    {
        $made = $this->of('construction', $new);
        $made->parts = match (true) {
            $new->class instanceof Stmt\Class_ => ['type' => $this->type($new->class)],
            $new->class instanceof Node\Name => ['type' => $this->writtenType($new->class)],
            default => ['callee' => $this->expression($new->class)],
        };
        $made->parts['arguments'] = $this->arguments($new);

        return $made;
    }

    /**
     * A construct PHP writes as a keyword and runs as a function: a call whose callee is the keyword's own name.
     *
     * @param  list<Expr>  $values
     */
    private function construct(string $keyword, Node $node, array $values, ?string $presenceForm = null): TreeNode
    {
        $start = $this->start($node);
        $made = $this->made('call', $start, $node instanceof Stmt ? $this->end($node) - 1 : $this->end($node), ['form' => $presenceForm]);
        $made->parts = [
            'callee' => $this->made('reference', $start, $start + strlen($keyword), ['name' => $keyword, 'form' => 'name']),
            'arguments' => array_map(function (Expr $value): TreeNode {
                $argument = $this->of('argument', $value);
                $argument->parts = ['value' => $this->expression($value)];

                return $argument;
            }, $values),
        ];

        return $made;
    }

    /**
     * @return list<TreeNode>
     */
    private function arguments(Expr\CallLike $call): array
    {
        return $call->isFirstClassCallable() ? [] : array_map($this->argument(...), $call->getArgs());
    }

    /**
     * A pipe `value |> callable` calls the callable with the value as its one argument, so it is written as that call.
     */
    private function pipe(Expr\BinaryOp $pipe): TreeNode
    {
        $argument = $this->of('argument', $pipe->left, []);
        $argument->parts = ['value' => $this->expression($pipe->left)];

        return $this->owning('call', $pipe, ['callee' => $this->expression($pipe->right), 'arguments' => [$argument]]);
    }

    private function argument(Node\Arg $argument): TreeNode
    {
        $made = $this->of('argument', $argument, [
            'label' => $argument->name?->toString(),
            'passing' => $argument->byRef ? 'reference' : null,
            'spread' => $argument->unpack ? 'positional' : null,
        ]);
        $made->parts = ['value' => $this->expression($argument->value)];

        return $made;
    }

    private function entry(Node\ArrayItem $item): TreeNode
    {
        $made = $this->of('entry', $item, ['expansion' => $item->unpack ? 'positional' : null]);
        $made->parts = array_filter(['key' => $item->key !== null ? $this->expression($item->key) : null, 'value' => $this->expression($item->value)]);

        return $made;
    }

    private function cast(Expr\Cast $cast): TreeNode
    {
        $start = $this->start($cast);
        $close = strpos($this->code, ')', $start);
        $type = $this->made('type-expression', $start + 1, $close === false ? $start + 1 : $close,
            ['form' => 'keyword', 'name' => $close === false ? null : trim(substr($this->code, $start + 1, $close - $start - 1))]);

        return $this->owning('cast', $cast, ['operand' => $this->expression($cast->expr), 'targetType' => $type], ['form' => 'checked']);
    }

    private function matchExpression(Expr\Match_ $match): TreeNode
    {
        $arms = array_map(fn (Node\MatchArm $arm) => $this->owning('arm', $arm, [
            'patterns' => array_map(fn (Expr $condition) => $this->owning('pattern', $condition, ['value' => $this->expression($condition)], ['form' => 'value']), $arm->conds ?? []),
            'body' => $this->expression($arm->body),
        ], ['default' => $arm->conds === null]), $match->arms);

        return $this->owning('dispatch', $match, ['subject' => $this->expression($match->cond), 'arms' => $arms], ['form' => 'match', 'exhaustive' => $match->getAttribute('exhaustive')]);
    }

    // Comments -------------------------------------------------------------------------------------------------------

    /**
     * Every comment of the file: its form, text and span, the node it is attached to, and what PHP's parser makes of
     * its words read as code.
     *
     * @param  list<TreeNode>  $nodes
     * @return list<array<string, mixed>>
     */
    public function comments(array $nodes): array
    {
        $starting = [];
        foreach ($nodes as $node) {
            if (! in_array($node->kind, ['file', 'module', 'block'], true) && ! isset($starting[$node->start])) {
                $starting[$node->start] = $node;
            }
        }
        $comments = [];
        foreach ($this->tokens as $token) {
            if (! in_array($token->id, [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $text = rtrim($token->text, "\r\n");
            $form = match (true) {
                $token->id === T_DOC_COMMENT => 'doc',
                str_starts_with($text, '/*') => 'block',
                default => 'line',
            };
            $comment = ['id' => count($comments), 'form' => $form, 'text' => $text, 'span' => [$token->pos, $token->pos + strlen($text), $token->line]];
            [$owner, $trailing] = $this->owner($token->pos, $token->pos + strlen($token->text), $nodes, $starting);
            if ($owner !== null) {
                $comment['attached'] = $owner->id;
            }
            if ($trailing) {
                $comment['trailing'] = true;
            }
            if ($form !== 'doc') {
                $comment['fragment'] = self::fragment($text);
            } else {
                $comment += array_filter(self::docblock($text));
                if (($refs = $this->references($text, $token->pos)) !== []) {
                    $comment['refs'] = $refs;
                }
            }
            $comments[] = $comment;
        }

        return $comments;
    }

    /**
     * What a docblock says: its prose lines before the first tag, and each tag with the type and the variable it is
     * about, as PHPDoc writes them (`@param int $count the count`).
     *
     * @return array{description: list<string>, tags: list<array<string, string>>}
     */
    private static function docblock(string $text): array
    {
        $lines = preg_split('/\R/', preg_replace(['#^/\*\*#', '#\*/$#'], '', $text)) ?: [];
        $description = [];
        $tags = [];
        foreach ($lines as $line) {
            $line = rtrim(preg_replace('/^\s*\*? ?/', '', $line) ?? '');
            if (preg_match('/^@([\w\\-]+)\s*(.*)$/', $line, $tag) === 1) {
                $tags[] = self::docTag($tag[1], $tag[2]);
            } elseif ($tags !== []) {
                $tags[array_key_last($tags)]['text'] = trim(($tags[array_key_last($tags)]['text'] ?? '').' '.trim($line));
            } else {
                $description[] = trim($line);
            }
        }
        while ($description !== [] && $description[0] === '') {
            array_shift($description);
        }
        while ($description !== [] && end($description) === '') {
            array_pop($description);
        }

        return ['description' => $description, 'tags' => array_map(static fn (array $tag): array => array_filter($tag, static fn (string $part): bool => $part !== ''), $tags)];
    }

    /**
     * One PHPDoc tag: a typed tag (`@param`, `@var`, `@return`, `@property`, `@throws` and their kin) writes a type
     * first and, for a variable, the `$name` it is about.
     *
     * @return array<string, string>
     */
    /**
     * @return list<array<string, mixed>>
     */
    private function references(string $text, int $at): array
    {
        preg_match_all('/@(?:see|link)\s+(\\\\?[A-Za-z_][\\\\\w]*)(?:::(\$?\w+))?(\(\))?(?=[\s,;)}]|\.(?!\w)|$)/', $text, $found, PREG_SET_ORDER);
        $enclosing = null;
        $enclosingParent = null;
        foreach ($this->typeSpans as [$start, $end, $symbol, $parent]) {
            if ($start <= $at && $at < $end) {
                $enclosing = $symbol;
                $enclosingParent = $parent;
            }
        }
        $refs = [];
        foreach ($found as $one) {
            $written = $one[1];
            $member = isset($one[2]) && $one[2] !== '' ? ltrim($one[2], '$') : null;
            $called = ($one[3] ?? '') !== '';
            $bare = ! str_contains($written, '\\') && $member === null;
            $readings = match (true) {
                in_array(strtolower($written), ['self', 'static'], true) && $enclosing !== null => [[$enclosing, $member]],
                strtolower($written) === 'parent' && $enclosingParent !== null => [[$enclosingParent, $member]],
                default => array_values(array_filter([
                    $bare && $called ? null : [$this->qualified($written), $member],
                    ! $bare && ! str_starts_with($written, '\\') ? [$written, $member] : null,
                    $bare && $enclosing !== null ? [$enclosing, $written] : null,
                    $bare && $enclosingParent !== null ? [$enclosingParent, $written] : null,
                ])),
            };
            $ref = ['text' => $written.($member !== null ? '::'.$member : '')];
            foreach ($readings as [$class, $named]) {
                $declared = $this->known?->declared($class);
                if ($declared !== null && ($named === null
                    || isset($declared['methods'][strtolower($named)])
                    || isset($declared['constants'][$named])
                    || isset($declared['properties'][$named]))) {
                    $ref['symbol'] = $named === null ? $declared['name'] : $declared['name'].'::'.$named;
                    break;
                }
            }
            if (! isset($ref['symbol'])) {
                $ref['ownedHere'] = $bare && $called && function_exists($written)
                    ? false
                    : strtok($readings[0][0] ?? $this->qualified($written), '\\') === strtok((string) $this->namespace, '\\');
            }
            $refs[] = $ref;
        }

        return $refs;
    }

    private function qualified(string $written): string
    {
        if (str_starts_with($written, '\\')) {
            return ltrim($written, '\\');
        }
        $head = (string) strtok($written, '\\');
        $alias = $this->aliases[strtolower($head)] ?? null;
        if ($alias !== null) {
            return $alias.substr($written, strlen($head));
        }

        return ($this->namespace !== null ? $this->namespace.'\\' : '').$written;
    }

    private function documentedReturn(Node\FunctionLike $function, TreeNode $written): void
    {
        $doc = $function->getDocComment()?->getText();
        if (($written->facts['name'] ?? null) !== 'array' || $doc === null
            || preg_match('/@(?:(?:phpstan|psalm)-)?return\s+(\S.*?)\s*(?:\*\/|\R|$)/', $doc, $found) !== 1) {
            return;
        }
        $type = self::documentedType(self::docTag('return', $found[1])['type'] ?? '');
        if ($type !== null) {
            $this->types[] = $type;
            $written->facts['resolvedType'] = array_key_last($this->types);
        }
    }

    /**
     * A property's @var type, when it names the element of a collection its written type leaves out
     * (`@var Collection<int, Line>|Lazy` on `Collection|Lazy`): each member of the documented union, with the
     * element of a generic class or array, named in the file's namespace and imports.
     */
    private function documentedVar(Stmt\Property $property, TreeNode $written): void
    {
        $doc = $property->getDocComment()?->getText();
        if ($doc === null || preg_match('/@(?:(?:phpstan|psalm)-)?var\s+(\S.*?)\s*(?:\*\/|\R|$)/', $doc, $found) !== 1) {
            return;
        }
        $members = array_map($this->documentedMember(...), self::topLevel(self::docTag('var', $found[1])['type'] ?? '', '|'));
        if (array_filter($members, static fn (array $member): bool => isset($member['element'])) === []) {
            return;
        }
        $said = implode('|', array_map(static fn (array $member): string => $member['text'], $members));
        $this->types[] = count($members) === 1 ? $members[0] : ['text' => $said, 'kind' => 'union', 'origin' => 'docblock', 'members' => $members];
        $written->facts['resolvedType'] = array_key_last($this->types);
    }

    /** @return array<string, mixed> */
    private function documentedMember(string $said): array
    {
        $said = trim($said);
        $listed = self::documentedType($said);
        if ($listed !== null) {
            return isset($listed['element']) ? ['element' => $this->documentedName($listed['element'])] + $listed : $listed;
        }
        if (preg_match('/^(\\?[A-Za-z_][\\\w]*)<(.+)>$/s', $said, $generic) === 1) {
            $args = array_map(fn (string $arg): array => $this->documentedName(self::documentedElement($arg)), self::topLevel($generic[2], ','));

            return ['text' => $said, 'kind' => 'named', 'name' => $this->qualified($generic[1]), 'origin' => 'docblock', 'args' => $args, 'element' => $args[array_key_last($args)]];
        }

        return $this->documentedName(self::documentedElement($said));
    }

    /**
     * @param  array<string, mixed>  $type
     * @return array<string, mixed>
     */
    private function documentedName(array $type): array
    {
        return ($type['kind'] ?? null) === 'named' ? ['name' => $this->qualified($type['name'])] + $type : $type;
    }

    /**
     * A doc type's parts split at a separator written outside any `<>`, `{}` or `()`.
     *
     * @return list<string>
     */
    private static function topLevel(string $said, string $separator): array
    {
        $parts = [];
        $depth = 0;
        $part = '';
        foreach (str_split($said) as $character) {
            $depth += match ($character) {
                '<', '{', '(' => 1,
                '>', '}', ')' => -1,
                default => 0,
            };
            if ($character === $separator && $depth === 0) {
                $parts[] = trim($part);
                $part = '';

                continue;
            }
            $part .= $character;
        }
        $parts[] = trim($part);

        return array_values(array_filter($parts, static fn (string $each): bool => $each !== ''));
    }

    /** @return array<string, mixed>|null */
    private static function documentedType(string $said): ?array
    {
        $said = trim($said);
        if (str_starts_with($said, 'array{')) {
            return ['text' => $said, 'kind' => 'object', 'origin' => 'docblock', 'nullable' => false, 'valueType' => true,
                'fields' => array_map(static fn (string $name): array => ['name' => $name, 'type' => ['text' => 'mixed', 'kind' => 'opaque', 'origin' => 'docblock']], array_keys(self::shapeKeys($said)))];
        }
        if (preg_match('/^array<\s*(?:string|array-key)\s*,\s*(.+)>$/', $said, $map) === 1) {
            $element = self::documentedElement($map[1]);

            return ['text' => $said, 'kind' => 'map', 'origin' => 'docblock', 'nullable' => false, 'valueType' => true, 'args' => [['text' => 'string', 'kind' => 'keyword', 'name' => 'string', 'origin' => 'docblock'], $element], 'element' => $element];
        }
        if (preg_match('/^(?:non-empty-)?(?:list|array)<(?:\s*int\s*,)?(.+)>$/', $said, $list) === 1 || preg_match('/^([\w\\\\]+)\[\]$/', $said, $list) === 1) {
            $element = self::documentedElement($list[1]);

            return ['text' => $said, 'kind' => 'array', 'origin' => 'docblock', 'nullable' => false, 'valueType' => true, 'args' => [$element], 'element' => $element];
        }

        return null;
    }

    /** @return array<string, string> */
    private static function documentedElement(string $said): array
    {
        $said = trim($said);
        $keywords = ['string', 'int', 'float', 'bool', 'mixed', 'array', 'null', 'object', 'callable', 'iterable', 'true', 'false'];
        if (in_array($said, $keywords, true) || str_starts_with($said, 'class-string') || str_contains($said, '|') || str_contains($said, '{')) {
            return ['text' => $said, 'kind' => 'keyword', 'name' => in_array($said, $keywords, true) ? $said : 'string', 'origin' => 'docblock'];
        }

        return ['text' => $said, 'kind' => 'named', 'name' => ltrim(preg_replace('/<.*$/s', '', $said) ?? $said, '\\'), 'origin' => 'docblock'];
    }

    /** @return array<string, true> */
    private static function shapeKeys(string $shape): array
    {
        $start = 1 + (int) strpos($shape, '{');
        $body = substr($shape, $start, (int) strrpos($shape, '}') - $start);
        $keys = [];
        $depth = 0;
        $entry = '';
        foreach (str_split($body.',') as $char) {
            $depth += match ($char) { '{', '<', '(' => 1, '}', '>', ')' => -1, default => 0 };
            if ($depth === 0 && $char === ',') {
                if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\??\s*:/', $entry, $key) === 1) {
                    $keys[$key[1]] = true;
                }
                $entry = '';

                continue;
            }
            $entry .= $char;
        }

        return $keys;
    }

    private static function docTag(string $name, string $rest): array
    {
        $tag = ['name' => $name, 'text' => trim($rest)];
        if (preg_match('/^(?:(?:phpstan|psalm)-)?method$/', $name) === 1) {
            if (preg_match('/^(?:static\s+)?(?:((?:[^\s<({]|<[^>]*>)+)\s+)?(\w+)\s*\((.*)$/s', trim($rest), $method) === 1) {
                $tag['type'] = $method[1];
                $tag['subject'] = $method[2];
            }

            return $tag;
        }
        if (preg_match('/^(?:(?:phpstan|psalm)-)?(param|var|return|returns|property|property-read|property-write|throws)$/', $name) !== 1) {
            return $tag;
        }
        if (preg_match('/^((?:[^\s<({]|<[^>]*>|\([^)]*\)|\{[^}]*\})+)\s*(\$\w+)?\s*(.*)$/s', trim($rest), $typed) === 1 && ! str_starts_with($typed[1], '$')) {
            $tag['type'] = $typed[1];
            $tag['subject'] = isset($typed[2]) ? ltrim($typed[2], '$') : '';
            $tag['text'] = trim($typed[3] ?? '');
        }

        return $tag;
    }

    /**
     * @param  list<TreeNode>  $nodes
     * @param  array<int, TreeNode>  $starting
     * @return array{TreeNode|null, bool}
     */
    private function owner(int $start, int $end, array $nodes, array $starting): array
    {
        $lineStart = $this->lines[$this->line($start) - 1];
        if (trim(substr($this->code, $lineStart, $start - $lineStart)) !== '') {
            foreach ($nodes as $node) {
                if ($node->start >= $lineStart && $node->start < $start && ! in_array($node->kind, ['file', 'module', 'block'], true)) {
                    return [$node, true];
                }
            }

            return [null, true];
        }
        $after = $end;
        while (true) {
            $gap = strspn($this->code, " \t\r\n", $after);
            if (substr_count(substr($this->code, $after, $gap), "\n") > 1) {
                return [null, false];
            }
            $after += $gap;
            $attribute = str_starts_with(substr($this->code, $after, 2), '#[');
            if (isset($starting[$after]) || $attribute || (! str_starts_with(substr($this->code, $after, 2), '//') && ! str_starts_with(substr($this->code, $after, 1), '#') && ! str_starts_with(substr($this->code, $after, 2), '/*'))) {
                break;
            }
            $closing = str_starts_with(substr($this->code, $after, 2), '/*') ? strpos($this->code, '*/', $after) : strpos($this->code, "\n", $after);
            $after = match (true) {
                $closing === false => strlen($this->code),
                default => $closing + (str_starts_with(substr($this->code, $after, 2), '/*') ? 2 : 0),
            };
        }

        return [$starting[$after] ?? null, false];
    }

    /**
     * What PHP's parser makes of a comment's words read as statements; the shared rule decides whether they are code.
     *
     * @return array<string, mixed>
     */
    private static function fragment(string $comment): array
    {
        static $parser = null;
        $text = trim(match (true) {
            str_starts_with($comment, '//') => substr($comment, 2),
            str_starts_with($comment, '#') => substr($comment, 1),
            default => substr($comment, 2, -2),
        });
        $errors = new Collecting();
        $parser ??= (new ParserFactory())->createForNewestSupportedVersion();
        $code = "<?php {$text}";
        $statements = $text === '' ? [] : ($parser->parse($code, $errors) ?? []);
        $read = ['context' => 'statement', 'diagnostics' => count($errors->getErrors()), 'statements' => count($statements)];
        if (count($statements) === 1 && $statements[0] instanceof Stmt\Expression) {
            // The lone expression's kind is what the translation makes of it, as for any expression of the file.
            $read['expressionKind'] = (new self($code, $parser->getTokens()))->expression($statements[0]->expr)->kind;
        }

        return $read;
    }
}
