<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

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
     * @param  list<ModuleFile>  $modules
     */
    private function __construct(private readonly array $modules) {}

    /**
     * Every C# file under $path, read by a bridge located for the scan — none when `dotnet` is missing,
     * and no bridge sought at all when there is no C# to read.
     *
     * @param  string|list<string>  $path
     */
    public static function scan(string|array $path, ExcludedPaths $excluded = new ExcludedPaths()): self
    {
        $files = self::filesUnder((array) $path, $excluded);

        if ($files === []) {
            return new self([]);
        }

        return Bridge::located()->mapOr(new self([]), static fn (Bridge $bridge): self => self::read($bridge, (array) $path, $files));
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
