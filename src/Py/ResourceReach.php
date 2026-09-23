<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Ast\Support\ResourcePopulation;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\PhpTypes\Option;

/**
 * What each function reaches directly — the functions from outside the project it calls, its verbs
 * (`fn:os.rename`, `fn:builtins.open`), and the classes it builds or names, its subjects (`type:…`). Two
 * places doing the same job in different words call the same outside functions, so a mechanism is known by
 * what it reaches rather than by the names its author chose. The Python twin of the backend's
 * {@see \JesseGall\CodeCommandments\Ast\Support\ResourceReach}, at the one granularity a path rule needs.
 */
final class ResourceReach
{
    private const string FUNCTION = 'fn:';

    private const string TYPE = 'type:';

    private ?ResourcePopulation $functions = null;

    public function __construct(private readonly Codebase $codebase) {}

    /**
     * Reach counted over every function, each named where it is declared.
     */
    public function functions(): ResourcePopulation
    {
        if ($this->functions !== null) {
            return $this->functions;
        }

        $reach = [];

        foreach ($this->codebase->whereFunction()->get() as $match) {
            if ($match->node instanceof FunctionDef) {
                $reach[$this->codebase->index()->declarationOf($match->node)] = $this->reachOf($match->node, $match->module);
            }
        }

        return $this->functions = ResourcePopulation::counting($reach);
    }

    /**
     * Is $resource something other than a verb — a class the function builds or names? A shared class is
     * the subject two functions work on, never evidence that one of them forgot a step.
     */
    public function isType(string $resource): bool
    {
        return ! str_starts_with($resource, self::FUNCTION);
    }

    /**
     * What $function, written in $module, reaches: its outside calls and the classes it names.
     *
     * @return array<string, true>
     */
    private function reachOf(FunctionDef $function, ModuleFile $module): array
    {
        $reached = [];

        foreach ($function->ownExpressions() as $expression) {
            $resource = $expression->isCall()
                ? $this->resourceCalled($expression->get('callee'), $function, $module)
                : $this->classNamed($expression, $module);

            $resource->inspect(static function (string $name) use (&$reached): void {
                $reached[$name] = true;
            });
        }

        return $reached;
    }

    /**
     * What calling $callee reaches — the class it builds, or the outside function it names. A function of
     * the project's own is a collaborator, not a resource.
     *
     * @return Option<string>
     */
    private function resourceCalled(Expr $callee, FunctionDef $caller, ModuleFile $module): Option
    {
        return $this->classNamed($callee, $module)->orElse(fn () => $this->outsideName($callee, $caller, $module)->map(static fn (string $name): string => self::FUNCTION . $name));
    }

    /**
     * The class $expression names, when mypy says it names one.
     *
     * @return Option<string>
     */
    private function classNamed(Expr $expression, ModuleFile $module): Option
    {
        if (! $expression->is(ExprKind::Name) && ! $expression->is(ExprKind::Attribute)) {
            return Option::none();
        }

        return $this->codebase->types()->at($module->file, $expression->start, $expression->end)
            ->andThen(static fn (Type $type) => $type->constructedClass())
            ->map(static fn (string $class): string => self::TYPE . $class);
    }

    /**
     * The dotted name $callee, called in $caller, has outside the project — through the module's imports, or
     * as a builtin — none for anything the project or the caller binds, or that cannot be named.
     *
     * @return Option<string>
     */
    private function outsideName(Expr $callee, FunctionDef $caller, ModuleFile $module): Option
    {
        $dotted = $callee->dottedName();

        if ($dotted === '') {
            return Option::none();
        }

        $parts = explode('.', $dotted);
        $imported = $module->importedNames()[$parts[0]] ?? null;

        if ($imported !== null) {
            $name = implode('.', [$imported, ...array_slice($parts, 1)]);

            return $this->codebase->ownsPackage(explode('.', $name)[0]) ? Option::none() : Option::some($name);
        }

        return count($parts) === 1 && ! $module->binds($dotted) && ! $caller->bindsLocally($dotted) ? Option::some("builtins.{$dotted}") : Option::none();
    }
}
