<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag a function/method whose parameter list is too long. A long list is
 * error-prone to call (positional soup), and usually signals missing structure
 * — related parameters want to be a value object / DTO, or you should pass the
 * typed object instead of its fields.
 *
 * Constructors are exempt by default: a Data/DTO/DI constructor legitimately
 * lists its fields/dependencies (and `from([...])` is how it is built).
 *
 *
 *
 * @method-generated-start
 * @method static includeConstructors(bool $on = true)
 * @method static maxParameters(int $value)
 * @method-generated-end
 */
#[IntroducedIn('1.79.0')]
class TooManyParametersProphet extends PhpCommandment
{
    private const DEFAULT_MAX = 6;

    public function description(): string
    {
        return 'Keep parameter lists short — group related parameters into an object';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A function or method declares more than the allowed number of '
                . 'parameters — a long positional list that is hard to call '
                . 'correctly and usually hides a missing value object.'
            )
            ->leaveWhen(
                'It is a constructor defining a data shape or injected dependencies '
                . '(exempt by default), or the parameters are genuinely unrelated '
                . 'and cohesive grouping would be artificial.'
            )
            ->whenUnsure(
                'If several parameters travel together, make them a DTO/value object. '
                . 'If a factory mirrors a constructor\'s whole parameter list, drop the '
                . 'factory and build the object directly (named args / from([...])).'
            );
    }

    public function detailedDescription(): string
    {
        $max = $this->maxParameters();

        return <<<SCRIPTURE
A long parameter list is a call-site hazard — positional arguments are easy to
transpose, optional ones pile up at the end, and the signature stops telling a
story. It almost always means a WORKER method is doing too much, or that several
parameters that travel together want to be a single value object.

Bad — a worker that should group cohesive parameters into a value object:
    public function place(int \$x, int \$y, int \$z, int \$w, int \$h, int \$d, int \$r): void { /* … */ }

Good — group them:
    public function place(Coordinates \$at, Dimensions \$size): void { /* … */ }

WHAT FIRES — a function, method, or closure declaring more than {$max}
parameters (configurable via `max_parameters`).

WHAT DOES NOT — a constructor (a Data/DTO/DI constructor lists its own fields/
dependencies), and a value object's CONSTRUCTION FACTORY — a static named
constructor that returns `self`/`static`/a class and builds an instance
(`new self`, `self::from`, `static::make`, even a sibling/dynamic `\$class::from`).
Its parameters ARE the object's own fields, exactly like the constructor it
mirrors — flagging it just pushes you to a parameter object that would mirror them
again. Set `include_constructors` to police constructors too (factories stay
exempt — they are construction).

Configure via:

    Backend\TooManyParametersProphet::class => [
        'max_parameters' => {$max},
        'include_constructors' => false,
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $max = $this->maxParameters();
        $includeConstructors = (bool) $this->config('include_constructors', false);
        $finder = new NodeFinder;
        $warnings = [];

        // Per-class context so a construction FACTORY can be recognised: a static
        // named-constructor that mirrors its class's own __construct (e.g. a value
        // object's typed `make()`) lists the same fields the constructor does, so it
        // earns the same exemption as the constructor — its params ARE the shape,
        // not a missing value object (#138).
        $constructionFactories = $this->constructionFactoryIds($finder, $ast);

        /** @var array<Node\Stmt\ClassMethod|Node\Stmt\Function_|Expr\Closure|Expr\ArrowFunction> $functions */
        $functions = $finder->findInstanceOf($ast, Node\FunctionLike::class);

        foreach ($functions as $function) {
            $count = count($function->getParams());

            if ($count <= $max) {
                continue;
            }

            [$label, $isConstructor] = $this->describe($function);

            $exempt = ($isConstructor || isset($constructionFactories[spl_object_id($function)])) && ! $includeConstructors;

            if ($exempt) {
                continue;
            }

            $warnings[] = $this->warningAt(
                $function->getStartLine(),
                sprintf(
                    '%s declares %d parameters (max %d) — a long parameter list is error-prone to call and usually signals missing structure. Group related parameters into a value object / DTO, or pass the typed object instead of its fields.',
                    $label,
                    $count,
                    $max,
                ),
                null,
                'too-many-params:' . $label,
            );
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * @return array{0: string, 1: bool}  [label, isConstructor]
     */
    private function describe(Node\FunctionLike $function): array
    {
        if ($function instanceof Node\Stmt\ClassMethod) {
            $name = $function->name->toString();

            return [$name . '()', strtolower($name) === '__construct'];
        }

        if ($function instanceof Node\Stmt\Function_) {
            return [$function->name->toString() . '()', false];
        }

        return ['closure', false];
    }

    /**
     * spl_object_id set of ClassMethods that are CONSTRUCTION FACTORIES — a static
     * named constructor of a class: it returns `self`/`static`/the class AND its body
     * builds an instance of that class (`new self/static/X`, or `self/static/X::from`/
     * `::make`/`::create`/`::of`). Such a factory's parameters ARE the object's own
     * fields/convenience inputs (typed named-argument construction), so it earns the
     * same exemption as the constructor — its long list is the data shape, not a
     * missing value object (#138). Generic: any class with a self-constructing factory.
     *
     * @param  array<Node>  $ast
     * @return array<int, true>
     */
    private function constructionFactoryIds(NodeFinder $finder, array $ast): array
    {
        $ids = [];

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            foreach ($class->getMethods() as $method) {
                if ($method->isStatic()
                    && strtolower($method->name->toString()) !== '__construct'
                    && $this->returnsClassType($method)
                    && $this->constructsAnObject($method)) {
                    $ids[spl_object_id($method)] = true;
                }
            }
        }

        return $ids;
    }

    /**
     * Whether the method's declared return type is an OBJECT type (`self`/`static`
     * or a class name) — i.e. it produces an instance — as opposed to void/scalar/
     * array. A factory of a value object commonly returns `self`/`static` or a
     * sibling type (e.g. a socket factory returning a `DataSocket` subtype).
     */
    private function returnsClassType(Node\Stmt\ClassMethod $method): bool
    {
        $type = $method->returnType;

        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        if ($type instanceof Node\Identifier) {
            return in_array(strtolower($type->toString()), ['self', 'static'], true);
        }

        return $type instanceof Node\Name;
    }

    /**
     * Whether the method body builds + returns an object — a `new …`, or a
     * `…::from`/`make`/`create`/`of` construction call (the receiver may be a class
     * name OR a dynamic `$class::from(...)`). That is what makes a static method a
     * construction factory rather than a long-param worker.
     */
    private function constructsAnObject(Node\Stmt\ClassMethod $method): bool
    {
        if ($method->stmts === null) {
            return false;
        }

        $finder = new NodeFinder;

        if ($finder->findFirstInstanceOf($method->stmts, Node\Expr\New_::class) !== null) {
            return true;
        }

        foreach ($finder->findInstanceOf($method->stmts, Node\Expr\StaticCall::class) as $call) {
            if ($call->name instanceof Node\Identifier
                && in_array(strtolower($call->name->toString()), ['from', 'make', 'create', 'of'], true)) {
                return true;
            }
        }

        return false;
    }

    private function maxParameters(): int
    {
        $value = $this->config('max_parameters', self::DEFAULT_MAX);

        return is_numeric($value) ? max(1, (int) $value) : self::DEFAULT_MAX;
    }
}
