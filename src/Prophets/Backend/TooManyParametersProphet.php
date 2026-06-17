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
 */
#[IntroducedIn('1.78.0')]
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
story. It almost always means related parameters want to be a single value
object, or that a method is doing too much.

Bad — a factory mirroring a whole data shape (19 positional params):
    public static function make(
        string \$name,
        string \$type,
        bool \$required,
        bool \$nullable,
        // … fifteen more …
    ): static {
        return static::from(compact('name', 'type', 'required', 'nullable', /* … */));
    }

Good — build the object directly with named arguments (no mirror factory):
    new SelectSocket(name: \$name, type: \$type, required: \$required, nullable: \$nullable, /* … */);

Good — group cohesive parameters into a value object:
    public function place(Coordinates \$at, Dimensions \$size): void { /* … */ }

WHAT FIRES — a function, method, or closure declaring more than {$max}
parameters (configurable via `max_parameters`).

WHAT DOES NOT — constructors (a Data/DTO/DI constructor lists its own
fields/dependencies); set `include_constructors` to police those too.

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

        /** @var array<Node\Stmt\ClassMethod|Node\Stmt\Function_|Expr\Closure|Expr\ArrowFunction> $functions */
        $functions = $finder->findInstanceOf($ast, Node\FunctionLike::class);

        foreach ($functions as $function) {
            $count = count($function->getParams());

            if ($count <= $max) {
                continue;
            }

            [$label, $isConstructor] = $this->describe($function);

            if ($isConstructor && ! $includeConstructors) {
                continue;
            }

            $warnings[] = $this->warningAt(
                $function->getStartLine(),
                sprintf(
                    '%s declares %d parameters (max %d) — a long parameter list is error-prone to call and usually signals missing structure. Group related parameters into a value object / DTO, or pass the typed object instead of its fields. (A factory that mirrors a whole constructor is the giveaway — build the object directly.)',
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

    private function maxParameters(): int
    {
        $value = $this->config('max_parameters', self::DEFAULT_MAX);

        return is_numeric($value) ? max(1, (int) $value) : self::DEFAULT_MAX;
    }
}
