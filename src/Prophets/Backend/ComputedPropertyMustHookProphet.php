<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\PackageDetector;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractClass;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;
use PhpParser\Node;

/**
 * Commandment: Computed properties must use property hooks, not constructor assignment.
 */
class ComputedPropertyMustHookProphet extends PhpCommandment
{
    private const COMPUTED_ATTRIBUTE = 'Spatie\\LaravelData\\Attributes\\Computed';

    public function supported(): bool
    {
        return PackageDetector::hasSpatieData();
    }

    public function description(): string
    {
        return 'Computed properties must use property hooks instead of constructor assignment';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Properties marked with #[Computed] should use PHP 8.4 property hooks to derive their
value, not be assigned in the constructor. Property hooks keep the computation close
to the declaration and make the intent clear.

Bad:
    class SearchData extends Data
    {
        #[Computed]
        public string|null $search;

        public function __construct(
            public RequestData $request,
        ) {
            $this->search = $this->request->getSearch();
        }
    }

Good:
    class SearchData extends Data
    {
        #[Computed]
        public string|null $search {
            get => $this->request->getSearch();
        }

        public function __construct(
            public RequestData $request,
        ) {}
    }
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractUseStatements::class)
            ->pipe(ExtractClass::class)
            ->returnRighteousIfNoClass()
            ->pipe(fn (PhpContext $ctx) => $this->findComputedPropertiesSetInConstructor($ctx))
            ->sinsFromMatches(
                fn (MatchResult $m) => sprintf(
                    'Computed property "$%s" is assigned in the constructor',
                    $m->groups['property']
                ),
                'Use a property hook instead: public type $property { get => ...; }'
            )
            ->judge();
    }

    private function findComputedPropertiesSetInConstructor(PhpContext $ctx): PhpContext
    {
        $matches = [];

        foreach ($ctx->classes as $class) {
            $computedProperties = $this->getComputedPropertyNames($class);

            if (empty($computedProperties)) {
                continue;
            }

            $constructor = $this->findConstructor($class);

            if ($constructor === null) {
                continue;
            }

            foreach ($this->findConstructorAssignments($constructor) as $assignment) {
                $propertyName = $assignment['name'];

                if (! in_array($propertyName, $computedProperties, true)) {
                    continue;
                }

                $matches[] = new MatchResult(
                    name: 'computed_property_constructor_assignment',
                    pattern: '',
                    match: '',
                    line: $assignment['line'],
                    offset: null,
                    content: sprintf('$this->%s = ...', $propertyName),
                    groups: [
                        'property' => $propertyName,
                    ],
                );
            }
        }

        return $ctx->with(matches: $matches);
    }

    /**
     * @return array<string>
     */
    private function getComputedPropertyNames(Node\Stmt\Class_ $class): array
    {
        $names = [];

        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\Property) {
                continue;
            }

            if ($this->astPropertyHasComputedAttribute($stmt)) {
                $names[] = $stmt->props[0]->name->toString();
            }
        }

        return $names;
    }

    /**
     * Check if an AST property node has the #[Computed] attribute.
     */
    private function astPropertyHasComputedAttribute(Node\Stmt\Property $property): bool
    {
        foreach ($property->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $name = $attr->name->toString();

                if ($name === 'Computed' || $name === self::COMPUTED_ATTRIBUTE || str_ends_with($name, '\\Computed')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Find the __construct method in a class.
     */
    private function findConstructor(Node\Stmt\Class_ $class): ?Node\Stmt\ClassMethod
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->toString() === '__construct') {
                return $stmt;
            }
        }

        return null;
    }

    /**
     * Find all $this->property = ... assignments in a constructor.
     *
     * @return array<array{name: string, line: int}>
     */
    private function findConstructorAssignments(Node\Stmt\ClassMethod $constructor): array
    {
        $assignments = [];

        if ($constructor->stmts === null) {
            return $assignments;
        }

        foreach ($constructor->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\Expression) {
                continue;
            }

            $expr = $stmt->expr;

            if (! $expr instanceof Node\Expr\Assign) {
                continue;
            }

            if (! $expr->var instanceof Node\Expr\PropertyFetch) {
                continue;
            }

            $var = $expr->var;

            if (! $var->var instanceof Node\Expr\Variable || $var->var->name !== 'this') {
                continue;
            }

            if ($var->name instanceof Node\Identifier) {
                $assignments[] = [
                    'name' => $var->name->toString(),
                    'line' => $stmt->getStartLine(),
                ];
            }
        }

        return $assignments;
    }

}
