<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Pipe;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Find declarations that pass an `array<string, mixed>` bag around —
 * parameters, properties (incl. constructor-promoted), and return types.
 *
 * Each one is a named value bag travelling as a raw array: the candidate
 * fix is a `Fluent`-based value class (plus `Castable`/`WithCastable` when
 * the bag lives on a Spatie Data object).
 *
 * Genuine dictionaries (`array<string, ConcreteType>`) are exempt — a
 * concrete value type means a real map, not a record in disguise.
 *
 * @implements Pipe<PhpContext, PhpContext>
 */
final class FindArrayBagDeclarations implements Pipe
{
    /**
     * Methods whose array params/returns are boundary signatures —
     * serialization outputs and vendor-interface implementations.
     */
    private const EXEMPT_METHODS = [
        'toArray', 'jsonSerialize', 'toJson',
        '__serialize', '__unserialize', '__debugInfo',
        'cast', 'transform',
        'rules', 'messages', 'attributes', 'casts',
    ];

    /**
     * Base classes that ARE the typed bag — inside them, raw array
     * boundaries are righteous.
     */
    private const BAG_BASE_CLASSES = [
        'Illuminate\\Support\\Fluent',
    ];

    private const SPATIE_DATA_CLASSES = [
        'Spatie\\LaravelData\\Data',
        'Spatie\\LaravelData\\Resource',
        'Spatie\\LaravelData\\Dto',
    ];

    /** @var list<string> */
    private array $exemptMethods = self::EXEMPT_METHODS;

    /**
     * @param  list<string>  $methods
     */
    public function withExemptMethods(array $methods): self
    {
        $this->exemptMethods = array_values(array_unique([...self::EXEMPT_METHODS, ...$methods]));

        return $this;
    }

    public function handle(mixed $input): mixed
    {
        if ($input->ast === null) {
            return $input->with(matches: []);
        }

        $nodeFinder = new NodeFinder;
        /** @var array<Node\Stmt\ClassLike> $classLikes */
        $classLikes = $nodeFinder->findInstanceOf($input->ast, Node\Stmt\ClassLike::class);

        $matches = [];

        foreach ($classLikes as $classLike) {
            if ($classLike instanceof Node\Stmt\Class_ && $classLike->name === null) {
                continue; // Anonymous classes implement vendor interfaces (Casts etc.)
            }

            if ($this->extendsOneOf($classLike, self::BAG_BASE_CLASSES, $input->useStatements)) {
                continue; // The bag class itself is the array boundary.
            }

            $className = $classLike->name?->toString() ?? '?';
            $isDataClass = $this->extendsOneOf($classLike, self::SPATIE_DATA_CLASSES, $input->useStatements);

            foreach ($classLike->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\Property) {
                    $this->collectProperty($stmt, $className, $isDataClass, $input->content, $matches);
                }
            }

            foreach ($classLike->getMethods() as $method) {
                $this->collectMethod($method, $className, $isDataClass, $input->content, $matches);
            }
        }

        return $input->with(matches: $matches);
    }

    /**
     * @param  array<MatchResult>  $matches
     */
    private function collectProperty(
        Node\Stmt\Property $property,
        string $className,
        bool $isDataClass,
        string $content,
        array &$matches,
    ): void {
        $doc = $property->getDocComment()?->getText();

        if ($doc === null) {
            return;
        }

        $annotation = $this->bagAnnotationIn($doc, 'var');

        if ($annotation === null || ! $this->isArrayNativeType($property->type)) {
            return;
        }

        foreach ($property->props as $prop) {
            $matches[] = $this->match(
                kind: $isDataClass ? 'data_property' : 'property',
                name: $prop->name->toString(),
                owner: $className,
                annotation: $annotation,
                line: $property->getStartLine(),
                content: $content,
            );
        }
    }

    /**
     * @param  array<MatchResult>  $matches
     */
    private function collectMethod(
        Node\Stmt\ClassMethod $method,
        string $className,
        bool $isDataClass,
        string $content,
        array &$matches,
    ): void {
        $methodName = $method->name->toString();

        if (in_array($methodName, $this->exemptMethods, true)) {
            return;
        }

        $doc = $method->getDocComment()?->getText() ?? '';

        foreach ($this->bagParamAnnotationsIn($doc) as $paramName => $annotation) {
            $param = $this->findParam($method, $paramName);

            if ($param === null || $param->variadic || ! $this->isArrayNativeType($param->type)) {
                continue;
            }

            $promoted = $methodName === '__construct' && $param->flags !== 0;

            $matches[] = $this->match(
                kind: $promoted
                    ? ($isDataClass ? 'data_property' : 'property')
                    : 'param',
                name: $paramName,
                owner: $promoted ? $className : $methodName,
                annotation: $annotation,
                line: $param->getStartLine(),
                content: $content,
            );
        }

        $returnAnnotation = $this->bagAnnotationIn($doc, 'return');

        if ($returnAnnotation !== null && $this->isArrayNativeType($method->returnType)) {
            $matches[] = $this->match(
                kind: 'return',
                name: $methodName,
                owner: $className,
                annotation: $returnAnnotation,
                line: $method->getStartLine(),
                content: $content,
            );
        }
    }

    private function match(
        string $kind,
        string $name,
        string $owner,
        string $annotation,
        int $line,
        string $content,
    ): MatchResult {
        return new MatchResult(
            name: $name,
            pattern: '',
            match: $annotation,
            line: $line,
            offset: null,
            content: $this->getSnippet($content, $line),
            groups: [
                'kind' => $kind,
                'name' => $name,
                'owner' => $owner,
                'annotation' => $annotation,
                'target' => $this->suggestedClassName($name, $kind),
            ],
        );
    }

    /**
     * Bag annotation behind a tag without a variable name (`@var`, `@return`).
     */
    private function bagAnnotationIn(string $doc, string $tag): ?string
    {
        $pattern = '/@' . $tag . '\s+[^$\n]*?(' . $this->bagTypePattern() . ')/i';

        return preg_match($pattern, $doc, $m) === 1 ? $m[1] : null;
    }

    /**
     * Map of `@param <bag> $name` annotations keyed by parameter name.
     *
     * @return array<string, string>
     */
    private function bagParamAnnotationsIn(string $doc): array
    {
        $pattern = '/@param\s+[^$\n]*?(' . $this->bagTypePattern() . ')[^$\n]*?\$(\w+)/i';

        if (preg_match_all($pattern, $doc, $m) === 0) {
            return [];
        }

        return array_combine($m[2], $m[1]);
    }

    /**
     * A string-keyed array whose value type declares nothing — the record
     * in disguise. `array<string, ConcreteType>` intentionally does NOT
     * match: that is a genuine dictionary.
     */
    private function bagTypePattern(): string
    {
        return 'array<\s*' . FindArrayStringIndexing::dictKeyTypePattern()
            . '\s*,\s*' . FindArrayStringIndexing::nonDictValueTypePattern()
            . '\s*>';
    }

    private function findParam(Node\Stmt\ClassMethod $method, string $name): ?Node\Param
    {
        foreach ($method->params as $param) {
            if ($param->var instanceof Node\Expr\Variable && $param->var->name === $name) {
                return $param;
            }
        }

        return null;
    }

    /**
     * Whether the native type can actually hold the annotated array —
     * a stale annotation on an already-typed declaration is not a sin.
     */
    private function isArrayNativeType(?Node $type): bool
    {
        if ($type === null) {
            return true;
        }

        if ($type instanceof Node\NullableType) {
            return $this->isArrayNativeType($type->type);
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $subType) {
                if ($this->isArrayNativeType($subType)) {
                    return true;
                }
            }

            return false;
        }

        if ($type instanceof Node\Identifier || $type instanceof Node\Name) {
            return in_array(strtolower($type->toString()), ['array', 'iterable'], true);
        }

        return false;
    }

    /**
     * @param  list<string>  $candidates
     * @param  array<string, string>  $useStatements
     */
    private function extendsOneOf(Node\Stmt\ClassLike $classLike, array $candidates, array $useStatements): bool
    {
        if (! $classLike instanceof Node\Stmt\Class_ || $classLike->extends === null) {
            return false;
        }

        $parent = $classLike->extends->toString();
        $resolved = $useStatements[$parent] ?? $parent;

        foreach ($candidates as $candidate) {
            $short = substr($candidate, (int) strrpos($candidate, '\\') + 1);

            if ($parent === $short || $resolved === $candidate || str_ends_with($resolved, '\\' . $short)) {
                return true;
            }
        }

        return false;
    }

    /**
     * StudlyCase class-name suggestion derived from the flagged name —
     * `$staticInputs` becomes `StaticInputs`.
     */
    private function suggestedClassName(string $name, string $kind): string
    {
        $studly = str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));

        return $kind === 'return' ? $studly . 'Bag' : $studly;
    }

    private function getSnippet(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
