<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\CodeCommandments\Support\HeldTool;

use Closure;
use JesseGall\CodeCommandments\ExcludedPaths;
use JesseGall\CodeCommandments\Files\FileQuery;
use JesseGall\CodeCommandments\ModuleCodebase;
use JesseGall\CodeCommandments\Support\FileTree;
use JesseGall\CodeCommandments\Support\Path;

/**
 * The C# files of a project as the Roslyn bridge read them, behind the same selectors the other engines
 * answer. Without the `dotnet` SDK there is no bridge and so no C#: the codebase is empty, and C# is
 * not judged rather than failing the run.
 */
final class Codebase implements ModuleCodebase
{
    /**
     * @var array<string, list<int>>|null  each method's declared symbol => the positions of the parameters it reads a dictionary by
     */
    private ?array $keyParameters = null;

    /**
     * @var list<list<string>>|null  each declared enum's member names, lower-cased
     */
    private ?array $enumCases = null;

    /**
     * @var list<string>|null  every class one of whose constants is compared as a case somewhere
     */
    private ?array $casedClasses = null;

    /**
     * @var list<string>|null  every enum this codebase declares, by symbol
     */
    private ?array $enums = null;

    /**
     * @param  list<ModuleFile>  $modules
     */
    private function __construct(private readonly array $modules) {}

    /**
     * Every C# file under $path, read by the bridge $held keeps (one sought for this scan by default) —
     * none when `dotnet` is missing, and no bridge sought at all when there is no C# to read.
     *
     * @param  string|list<string>  $path
     */
    public static function scan(string|array $path, ExcludedPaths $excluded = new ExcludedPaths(), HeldTool $held = new HeldTool(Bridge::class)): self
    {
        $files = self::filesUnder((array) $path, $excluded);

        if ($files === []) {
            return new self([]);
        }

        return $held->tool()->mapOr(new self([]), static fn (Bridge $bridge): self => self::read($bridge, (array) $path, $files));
    }

    /**
     * Every C# file under $path, read by $bridge — a bridge its holder keeps warm across reads.
     *
     * @param  string|list<string>  $path
     */
    public static function readBy(Bridge $bridge, string|array $path, ExcludedPaths $excluded = new ExcludedPaths()): self
    {
        $files = self::filesUnder((array) $path, $excluded);

        return $files === [] ? new self([]) : self::read($bridge, (array) $path, $files);
    }

    /**
     * $source read as one C# file — written to a folder of its own for the bridge to read.
     */
    public static function fromString(string $source, string $path = 'Module.cs'): self
    {
        $dir = sys_get_temp_dir() . '/cc-cs-' . uniqid();
        mkdir($dir);
        file_put_contents("{$dir}/{$path}", $source);

        try {
            return self::scan($dir);
        } finally {
            unlink("{$dir}/{$path}");
            rmdir($dir);
        }
    }

    public function focusedOn(string ...$paths): static
    {
        $wanted = Path::setOf($paths);

        return new self(array_values(array_filter($this->modules, static fn (ModuleFile $module): bool => isset($wanted[Path::resolved($module->file)]))));
    }

    public function fileCount(): int
    {
        return count($this->modules());
    }

    /**
     * @return list<ModuleFile>
     */
    public function modules(): array
    {
        return $this->modules;
    }

    public function whereFile(): FileQuery
    {
        return new FileQuery(array_map(static fn (ModuleFile $module): string => $module->file, $this->modules));
    }

    /**
     * Every type declaration — class, record, struct, interface, enum.
     */
    public function whereType(): Query
    {
        return $this->whereNode(static fn (Node $node): bool => $node->role === 'member' && str_ends_with($node->kind, 'Declaration') && self::isTypeKind($node->kind));
    }

    /**
     * Every member that runs a body — a method, constructor, accessor, operator or local function.
     */
    public function whereFunction(): Query
    {
        return $this->whereNode(static fn (Node $node): bool => $node->functionBody()->isSome() && ! $node->isExpression());
    }

    public function whereMethodDeclaration(): Query
    {
        return $this->whereNode(static fn (Node $node): bool => $node->is('MethodDeclaration'));
    }

    /**
     * Every statement — not the declarations, parameters and clauses the tree gives nodes of their own.
     */
    public function whereStatement(): Query
    {
        return $this->whereNode(static fn (Node $node): bool => $node->role === 'statement');
    }

    /**
     * Every node $select keeps, expressions included.
     *
     * @param  Closure(Node): bool  $select
     */
    public function whereNode(Closure $select): Query
    {
        return new Query($this->pairs(...), $select);
    }

    public function whereCall(): Query
    {
        return $this->whereExpression(static fn (Node $node): bool => $node->isCall());
    }

    /**
     * Every expression $select keeps.
     *
     * @param  Closure(Node): bool  $select
     */
    public function whereExpression(Closure $select): Query
    {
        return new Query($this->expressionPairs(...), $select);
    }

    private static function isTypeKind(string $kind): bool
    {
        return in_array($kind, ['ClassDeclaration', 'RecordDeclaration', 'RecordStructDeclaration', 'StructDeclaration', 'InterfaceDeclaration', 'EnumDeclaration'], true);
    }

    /**
     * @return list<array{0: Node, 1: ModuleFile}>
     */
    private function pairs(): array
    {
        $pairs = [];

        foreach ($this->modules as $module) {
            foreach ([...$module->nodes(), ...$module->expressions()] as $node) {
                $pairs[] = [$node, $module];
            }
        }

        return $pairs;
    }

    /**
     * @return list<array{0: Node, 1: ModuleFile}>
     */
    private function expressionPairs(): array
    {
        $pairs = [];

        foreach ($this->modules as $module) {
            foreach ($module->expressions() as $expression) {
                $pairs[] = [$expression, $module];
            }
        }

        return $pairs;
    }

    /**
     * Does $call hand a string written in the source to a parameter the method it calls reads a
     * dictionary by — a lookup helper handed the key, which reads the record by name just the same?
     */
    public function passesLiteralKey(Node $call): bool
    {
        if ($call->target === null) {
            return false;
        }

        $positions = $this->keyParameters()[$call->target->symbol()] ?? [];
        $arguments = $call->arguments();

        return array_any($positions, static fn (int $position): bool => ($arguments[$position] ?? null)?->isConstant() === true && $arguments[$position]->type?->name === 'global::System.String');
    }

    /**
     * Do $literals — two or more of them — all name members of one enum this codebase declares, as a
     * string on the wire would spell them in any case?
     *
     * @param  list<string>  $literals
     */
    public function enumMirroredBy(array $literals): bool
    {
        $names = array_values(array_unique(array_map(strtolower(...), $literals)));

        return count($names) >= 2 && array_any($this->enumCases(), static fn (array $cases): bool => array_diff($names, $cases) === []);
    }

    /**
     * Is a constant of the class $symbol compared as a case anywhere — `status == Status.Paid`,
     * `case Status.Paid:`, `Status.Paid => …`? A constant only ever handed on as a name is not a case.
     */
    public function comparesAsACase(string $symbol): bool
    {
        return in_array($symbol, $this->casedClasses ??= $this->casedClasses(), true);
    }

    /**
     * @return list<string>
     */
    private function casedClasses(): array
    {
        $classes = [];

        foreach ($this->modules as $module) {
            foreach ($module->expressions() as $expression) {
                if ($expression->is('SimpleMemberAccessExpression') && $expression->constant && $module->isComparedAsACase($expression)) {
                    $classes[] = (string) $expression->children[0]->type?->name;
                }
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * Does this codebase declare the enum $symbol?
     */
    public function declaresEnum(string $symbol): bool
    {
        return in_array($symbol, $this->enums ??= array_map(static fn (NodeMatch $enum): string => (string) $enum->node->symbol, $this->whereNode(static fn (Node $node): bool => $node->is('EnumDeclaration'))->get()), true);
    }

    /**
     * @return list<list<string>>
     */
    private function enumCases(): array
    {
        return $this->enumCases ??= array_map(
            static fn (NodeMatch $enum): array => array_map(static fn (Node $member): string => strtolower((string) $member->name), array_filter($enum->node->children(), static fn (Node $child): bool => $child->is('EnumMemberDeclaration'))),
            $this->whereNode(static fn (Node $node): bool => $node->is('EnumDeclaration'))->get(),
        );
    }

    /**
     * @return array<string, list<int>>
     */
    private function keyParameters(): array
    {
        if ($this->keyParameters !== null) {
            return $this->keyParameters;
        }

        $this->keyParameters = [];

        foreach ($this->whereMethodDeclaration()->get() as $method) {
            $list = array_values(array_filter($method->node->children(), static fn (Node $child): bool => $child->is('ParameterList')))[0] ?? null;
            $names = array_map(static fn (Node $parameter): ?string => $parameter->name, $list?->children() ?? []);
            $keys = array_filter(
                array_merge([], ...array_map(static fn (Node $expression): array => $expression->flatten(), $method->node->outermostExpressions())),
                static fn (Node $read): bool => $read->isKeyedRead()
                    && ($read->arguments()[0] ?? null)?->is('IdentifierName') === true
                    && $read->readsDictionaryNamed($names),
            );
            $positions = array_values(array_unique(array_filter(array_map(static fn (Node $read): int|false => array_search($read->arguments()[0]->name, $names, true), $keys), static fn (int|false $position): bool => $position !== false)));

            if ($method->node->symbol !== null && $positions !== []) {
                $this->keyParameters[$method->node->symbol] = $positions;
            }
        }

        return $this->keyParameters;
    }

    /**
     * The C# files under $roots the walk every engine shares lets through.
     *
     * @param  list<string>  $roots
     * @return list<string>
     */
    private static function filesUnder(array $roots, ExcludedPaths $excluded): array
    {
        return array_merge(...array_map(static fn (string $root): array => iterator_to_array(FileTree::filesIn($root, 'cs', $excluded), false), $roots));
    }

    /**
     * $files read by $bridge, which compiles every project under $roots so each type resolves and
     * writes back only $files — each named as the walk named it, as every engine names its files,
     * though the bridge answers with links resolved.
     *
     * @param  list<string>  $roots
     * @param  list<string>  $files
     */
    private static function read(Bridge $bridge, array $roots, array $files): self
    {
        $named = array_combine(array_map(Path::resolved(...), $files), $files);

        return new self(array_map(static fn (WrittenFile $written) => ModuleFile::fromBridge($written, $named[$written->path]), $bridge->read($roots, $files)->files));
    }
}
