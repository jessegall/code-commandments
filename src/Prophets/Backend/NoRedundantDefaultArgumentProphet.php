<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionClassConstant;
use ReflectionMethod;
use Throwable;

/**
 * A `self::from([...])` / `new self(...)` call that passes a value equal to the
 * constructor parameter's default just restates the default — PHP (and Spatie
 * Data's `from()`) already applies it for any omitted argument. The classic
 * case is an empty `clean()` factory on a Data class that spells out
 * `'thing' => T_Array::empty()` for a property already declared
 * `array $thing = T_Array::EMPTY`.
 *
 * Values are compared by their RESOLVED UNDERLYING VALUE, read from the type's
 * own source via reflection — a class constant's real value, and a no-arg
 * static factory's source-level `return`. So `T_Array::EMPTY`,
 * `T_Array::empty()` and `[]` match because they ARE the same value, not
 * because their names are special-cased — rename the type and this still works.
 *
 * Auto-fixable: `repent` drops the redundant keys/arguments.
 */
#[IntroducedIn('1.109.0')]
class NoRedundantDefaultArgumentProphet extends PhpCommandment implements SinRepenter
{
    /** @var array<string, array{0: ?string, 1: array<string, string>, 2: ?Node\Stmt\Class_}> */
    private array $fileClassCache = [];

    public function description(): string
    {
        return 'Do not pass an argument equal to the parameter default — the default is already applied';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Cosmetic;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('a `self::from([...])` / `new self(...)` keys an argument to a value that equals the constructor parameter\'s declared default — it restates a default the framework already applies.')
            ->leaveWhen('the value only LOOKS like the default but resolves to a different value, or the parameter has no default so the argument is required.')
            ->whenUnsure('run `repent` — it only drops keys whose RESOLVED value provably equals the parameter\'s resolved default.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Passing an argument that equals the parameter's default is noise. PHP applies
the default for any omitted argument, and Spatie\LaravelData's `from()` applies
constructor defaults for any key not present — so spelling the default out
again says nothing.

Bad — an empty factory that restates every default:
    public function __construct(
        public readonly array $errors,
        public readonly array $warnings = T_Array::EMPTY,
        public readonly array $advisories = T_Array::EMPTY,
    ) {}

    public static function clean(): self
    {
        return self::from([
            'errors' => T_Array::empty(),
            'warnings' => T_Array::empty(),     // = the default → bogus
            'advisories' => T_Array::empty(),   // = the default → bogus
        ]);
    }

Good — pass only what differs from the default:
    public static function clean(): self
    {
        return self::from(['errors' => T_Array::empty()]);
    }

(And if EVERY field has a default, the factory collapses to `new self()`.)

Equality is by RESOLVED VALUE, not spelling: a class constant's real value and a
no-arg static factory's source `return` are read via reflection, so
`T_Array::EMPTY`, `T_Array::empty()` and `[]` match because they ARE the same
value — nothing is keyed off the literal name `T_Array`.

WHAT FIRES — a `self::from([...])` / `static::from([...])` / `new self(...)` /
`new static(...)` (constructor in the same class) where a keyed array item or a
named argument resolves to the parameter's default value.

WHAT DOES NOT — a parameter with NO default (the argument is required); a value
whose resolution differs from the default, or can't be resolved (conservative —
unresolved is never reported); positional arguments.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];

        foreach ($this->findings($ast, $content) as $finding) {
            $warnings[] = $this->warningAt($finding['line'], $finding['message'], $finding['snippet'], $finding['symbol'], true);
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    public function canRepent(string $filePath): bool
    {
        return str_ends_with($filePath, '.php');
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (! $this->canRepent($filePath)) {
            return RepentanceResult::unchanged();
        }

        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        $edits = [];
        $penance = [];

        foreach ($this->findings($ast, $content) as $finding) {
            $edits[] = $finding['edit'];
            $penance[] = $finding['penance'];
        }

        if ($edits === []) {
            return RepentanceResult::unchanged();
        }

        usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . substr($content, $edit['end'] + 1);
        }

        return RepentanceResult::absolved($content, $penance);
    }

    /**
     * @param  array<Node>  $ast
     * @return list<array{line: int, message: string, snippet: string, symbol: string, penance: string, edit: array{start: int, end: int}}>
     */
    private function findings(array $ast, string $content): array
    {
        $finder = new NodeFinder;
        $findings = [];

        foreach ($this->namespaceScopes($ast) as [$namespace, $uses, $scope]) {
            foreach ($finder->findInstanceOf($scope, Node\Stmt\Class_::class) as $class) {
                if ($class->name === null) {
                    continue;
                }

                $defaults = $this->constructorDefaults($class, $namespace, $uses);

                if ($defaults === []) {
                    continue;
                }

                $short = $class->name->toString();

                foreach ($finder->findInstanceOf($class->stmts, Expr\StaticCall::class) as $call) {
                    if (! $this->isSameClassRef($call->class, $short)
                        || ! $call->name instanceof Node\Identifier
                        || strtolower($call->name->toString()) !== 'from'
                        || $call->args === []
                        || ! $call->args[0] instanceof Node\Arg
                        || ! $call->args[0]->value instanceof Expr\Array_
                    ) {
                        continue;
                    }

                    foreach ($call->args[0]->value->items as $item) {
                        if ($item instanceof Node\Expr\ArrayItem
                            && $item->key instanceof Node\Scalar\String_
                            && $this->isRedundant($item->key->value, $item->value, $defaults, $namespace, $uses)
                        ) {
                            $findings[] = $this->findingFor($item->key->value, $item, $content);
                        }
                    }
                }

                foreach ($finder->findInstanceOf($class->stmts, Expr\New_::class) as $new) {
                    if (! $new->class instanceof Node\Name || ! $this->isSameClassRef($new->class, $short)) {
                        continue;
                    }

                    foreach ($new->args as $arg) {
                        if ($arg instanceof Node\Arg
                            && $arg->name instanceof Node\Identifier
                            && $this->isRedundant($arg->name->toString(), $arg->value, $defaults, $namespace, $uses)
                        ) {
                            $findings[] = $this->findingFor($arg->name->toString(), $arg, $content);
                        }
                    }
                }
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, string>  $uses
     * @return array<string, string>  param name => resolved default token
     */
    private function constructorDefaults(Node\Stmt\Class_ $class, ?string $namespace, array $uses): array
    {
        $constructor = $class->getMethod('__construct');

        if ($constructor === null) {
            return [];
        }

        $self = $namespace !== null && $namespace !== '' ? $namespace . '\\' . $class->name->toString() : $class->name->toString();
        $defaults = [];

        foreach ($constructor->params as $param) {
            if ($param->default === null || ! $param->var instanceof Expr\Variable || ! is_string($param->var->name)) {
                continue;
            }

            $token = $this->resolveToken($param->default, $namespace, $uses, $self);

            if ($token !== null) {
                $defaults[$param->var->name] = $token;
            }
        }

        return $defaults;
    }

    /**
     * @param  array<string, string>  $defaults
     * @param  array<string, string>  $uses
     */
    private function isRedundant(string $name, Expr $value, array $defaults, ?string $namespace, array $uses): bool
    {
        if (! isset($defaults[$name])) {
            return false;
        }

        $token = $this->resolveToken($value, $namespace, $uses, null);

        return $token !== null && $token === $defaults[$name];
    }

    /**
     * Resolve an expression to a canonical token for its UNDERLYING VALUE,
     * following class constants and no-arg static factories into their source.
     * Returns null when the value can't be determined (treated as not-equal).
     *
     * @param  array<string, string>  $uses
     */
    private function resolveToken(Expr $expr, ?string $namespace, array $uses, ?string $self): ?string
    {
        if ($expr instanceof Expr\Array_) {
            return $expr->items === [] ? $this->token([]) : null;
        }

        if ($expr instanceof Node\Scalar\String_) {
            return $this->token($expr->value);
        }

        if ($expr instanceof Node\Scalar\Int_) {
            return $this->token($expr->value);
        }

        if ($expr instanceof Node\Scalar\Float_) {
            return $this->token($expr->value);
        }

        if ($expr instanceof Expr\ConstFetch) {
            return match (strtolower($expr->name->toString())) {
                'null' => $this->token(null),
                'true' => $this->token(true),
                'false' => $this->token(false),
                default => null,
            };
        }

        if ($expr instanceof Expr\ClassConstFetch && $expr->name instanceof Node\Identifier && $expr->class instanceof Node\Name) {
            $fqcn = $this->resolveClass($expr->class, $namespace, $uses, $self);

            if ($fqcn === null) {
                return null;
            }

            if (strtolower($expr->name->toString()) === 'class') {
                return $this->token($fqcn);
            }

            try {
                return $this->token((new ReflectionClassConstant($fqcn, $expr->name->toString()))->getValue());
            } catch (Throwable) {
                return null;
            }
        }

        if ($expr instanceof Expr\StaticCall && $expr->name instanceof Node\Identifier && $expr->class instanceof Node\Name && $expr->args === []) {
            $fqcn = $this->resolveClass($expr->class, $namespace, $uses, $self);

            return $fqcn === null ? null : $this->resolveStaticReturn($fqcn, $expr->name->toString());
        }

        return null;
    }

    /**
     * A no-arg static factory's value, read from its source `return` (resolved
     * in the declaring file's namespace/use context, with `self` bound to the
     * declaring class). Null when it isn't a single resolvable return.
     */
    private function resolveStaticReturn(string $fqcn, string $method): ?string
    {
        try {
            $reflection = new ReflectionMethod($fqcn, $method);

            if (! $reflection->isStatic() || $reflection->getNumberOfRequiredParameters() > 0) {
                return null;
            }

            $file = $reflection->getFileName();

            if ($file === false) {
                return null;
            }

            [$namespace, $uses, $class] = $this->classFromFile($file, $reflection->getDeclaringClass()->getShortName());

            if ($class === null) {
                return null;
            }

            $node = $class->getMethod($method);
            $statements = array_values(array_filter($node->stmts ?? [], static fn (Node $s): bool => ! $s instanceof Node\Stmt\Nop));

            if (count($statements) !== 1 || ! $statements[0] instanceof Node\Stmt\Return_ || $statements[0]->expr === null) {
                return null;
            }

            $declaringFqcn = $reflection->getDeclaringClass()->getName();

            return $this->resolveToken($statements[0]->expr, $namespace, $uses, $declaringFqcn);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Parse a file (cached) and return [namespace, uses, the named class node].
     *
     * @return array{0: ?string, 1: array<string, string>, 2: ?Node\Stmt\Class_}
     */
    private function classFromFile(string $file, string $shortClass): array
    {
        $key = $file . '::' . $shortClass;

        if (isset($this->fileClassCache[$key])) {
            return $this->fileClassCache[$key];
        }

        $result = [null, [], null];

        $source = @file_get_contents($file);

        if ($source !== false) {
            $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);

            if ($ast !== null) {
                foreach ($this->namespaceScopes($ast) as [$namespace, $uses, $scope]) {
                    foreach ((new NodeFinder)->findInstanceOf($scope, Node\Stmt\Class_::class) as $class) {
                        if ($class->name !== null && $class->name->toString() === $shortClass) {
                            $result = [$namespace, $uses, $class];
                            break 2;
                        }
                    }
                }
            }
        }

        return $this->fileClassCache[$key] = $result;
    }

    private function token(mixed $value): ?string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'bool:' . ($value ? '1' : '0'),
            is_int($value) => 'int:' . $value,
            is_float($value) => 'float:' . $value,
            is_string($value) => 'string:' . $value,
            is_array($value) => $value === [] ? 'array:[]' : (is_string($json = json_encode($value)) ? 'array:' . $json : null),
            default => null,
        };
    }

    /**
     * @param  array<string, string>  $uses
     */
    private function resolveClass(Node\Name $name, ?string $namespace, array $uses, ?string $self): ?string
    {
        $value = $name->toString();

        return match (strtolower($value)) {
            'self', 'static' => $self,
            'parent' => null,
            default => $this->resolveFqcn($name, $uses, $namespace),
        };
    }

    private function isSameClassRef(Node $class, string $short): bool
    {
        if (! $class instanceof Node\Name) {
            return false;
        }

        return in_array(strtolower($class->toString()), ['self', 'static'], true) || $class->getLast() === $short;
    }

    /**
     * @return array{line: int, message: string, snippet: string, symbol: string, penance: string, edit: array{start: int, end: int}}
     */
    private function findingFor(string $name, Node $node, string $content): array
    {
        $line = $node->getStartLine();

        return [
            'line' => $line,
            'message' => sprintf('Argument `%s` restates the parameter\'s default — drop it, the default is applied automatically.', $name),
            'snippet' => $this->lineSnippet($content, $line),
            'symbol' => 'redundant-default:' . $name,
            'penance' => "Dropped redundant default argument `{$name}`",
            'edit' => $this->removalRange($node, $content),
        ];
    }

    /**
     * @return array{start: int, end: int}
     */
    private function removalRange(Node $node, string $content): array
    {
        $start = $node->getStartFilePos();
        $i = $node->getEndFilePos() + 1;
        $len = strlen($content);

        while ($i < $len && ($content[$i] === ' ' || $content[$i] === "\t")) {
            $i++;
        }

        if ($i < $len && $content[$i] === ',') {
            $i++;
        }

        while ($i < $len && ($content[$i] === ' ' || $content[$i] === "\t")) {
            $i++;
        }

        if ($i < $len && $content[$i] === "\n") {
            $i++;
            $lineStart = strrpos(substr($content, 0, $start), "\n");
            $start = $lineStart === false ? 0 : $lineStart + 1;
        }

        return ['start' => $start, 'end' => $i - 1];
    }


    /**
     * @param  array<Node>  $ast
     * @return list<array{0: ?string, 1: array<string, string>, 2: array<Node>}>
     */
    private function namespaceScopes(array $ast): array
    {
        $out = [];

        foreach ($ast as $node) {
            $namespace = null;
            $scope = [$node];

            if ($node instanceof Node\Stmt\Namespace_) {
                $namespace = $node->name?->toString();
                $scope = $node->stmts;
            }

            $out[] = [$namespace, $this->collectUses($scope), $scope];
        }

        return $out;
    }

    /**
     * @param  array<Node>  $stmts
     * @return array<string, string>
     */
    private function collectUses(array $stmts): array
    {
        $uses = [];

        foreach ($stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\Use_) {
                continue;
            }

            foreach ($stmt->uses as $useUse) {
                $uses[$useUse->alias?->toString() ?? $useUse->name->getLast()] = $useUse->name->toString();
            }
        }

        return $uses;
    }

    /**
     * @param  array<string, string>  $uses
     */
    private function resolveFqcn(Node\Name $name, array $uses, ?string $namespace): string
    {
        if ($name->isFullyQualified()) {
            return ltrim($name->toString(), '\\');
        }

        $parts = explode('\\', $name->toString());

        if (isset($uses[$parts[0]])) {
            $parts[0] = $uses[$parts[0]];

            return implode('\\', $parts);
        }

        if ($namespace !== null && $namespace !== '') {
            return $namespace . '\\' . $name->toString();
        }

        return $name->toString();
    }
}
