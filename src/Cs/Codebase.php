<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use Closure;
use JesseGall\CodeCommandments\ExcludedPaths;
use JesseGall\CodeCommandments\Files\FileQuery;
use JesseGall\CodeCommandments\ModuleCodebase;
use JesseGall\CodeCommandments\Support\FileTree;
use JesseGall\CodeCommandments\Support\HeldTool;
use JesseGall\CodeCommandments\Support\Path;
use JesseGall\PhpTypes\Option;

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
     * @var array<string, Node>|null  each declared method's symbol => its declaration
     */
    private ?array $declarations = null;

    /**
     * @var array<string, true>|null  the names of the methods handed out as delegates somewhere in the codebase
     */
    private ?array $handedOut = null;

    /**
     * @var array<string, Node>|null  each type the codebase declares, by symbol
     */
    private ?array $typeDeclarations = null;

    /**
     * @var array<string, list<NodeMatch>>|null  each method's declared symbol => the calls the compiler resolved to it
     */
    private ?array $callers = null;

    /**
     * @var array<string, list<Node>>|null  each parameter (`method#position`) => the types whose string constants some call fills it with
     */
    private ?array $vocabularies = null;

    /**
     * @var list<list<string>>|null  each declared enum's member names, lower-cased
     */
    private ?array $enumCases = null;

    /**
     * @var list<string>|null  every class one of whose constants is compared as a case somewhere
     */
    private ?array $casedClasses = null;

    /**
     * @var array<string, list<string>>|null  every enum this codebase declares, by symbol => its member names
     */
    private ?array $enums = null;

    /**
     * @var array<string, Node>|null  every record this codebase declares, by symbol
     */
    private ?array $records = null;

    /**
     * @var array<string, int>|null  every type this codebase declares, by symbol
     */
    private ?array $types = null;

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
        return $this->whereNode(static fn (Node $node): bool => $node->isTypeDeclaration());
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
     * The method $call reaches, when this codebase declares it — none for a library's method, or a call the
     * compiler did not resolve.
     *
     * @return Option<Node>
     */
    public function declarationOf(Node $call): Option
    {
        if ($this->declarations === null) {
            $this->declarations = [];

            foreach ($this->whereMethodDeclaration()->get() as $method) {
                $this->declarations[(string) $method->node->symbol] = $method->node;
            }
        }

        return Option::fromNullable($call->target === null ? null : $this->declarations[$call->target->symbol()] ?? null);
    }

    /**
     * The constant that already names $value in the parameter $call fills at $position — `Token.BraceOpen` for `"{"`
     * handed where another call hands `Token.Colon` — none when that parameter is never spelled by name, which is
     * the answer for almost every string.
     *
     * @return Option<string>
     */
    public function constantNaming(Node $call, int $position, string $value): Option
    {
        $this->vocabularies ??= $this->vocabularies();

        foreach ($this->vocabularies["{$call->target?->symbol()}#{$position}"] ?? [] as $type) {
            $name = $type->stringConstants()[$value] ?? null;

            if ($name !== null) {
                return Option::some("{$type->name}.{$name}");
            }
        }

        return Option::none();
    }

    /**
     * Every parameter of the codebase's own methods some call fills with a string constant of one of its types, with
     * those types. A library method's parameter takes values from every vocabulary at once, so it is no slot one
     * vocabulary owns.
     *
     * @return array<string, list<Node>>
     */
    private function vocabularies(): array
    {
        $vocabularies = [];

        foreach ($this->whereCall()->get() as $call) {
            if ($call->node->target === null || ! $call->node->passesByPosition() || $this->declarationOf($call->node)->isNone()) {
                continue;
            }

            foreach ($call->node->arguments() as $position => $argument) {
                $owner = $argument->is('SimpleMemberAccessExpression') ? $this->typeDeclared(rtrim((string) $argument->children[0]->type?->name, '?')) : Option::none();
                $slot = "{$call->node->target->symbol()}#{$position}";

                if ($owner->isSomeAnd(static fn (Node $type): bool => in_array($argument->children[1]->name, $type->stringConstants(), true) && ! in_array($type, $vocabularies[$slot] ?? [], true))) {
                    $vocabularies[$slot][] = $owner->unwrap();
                }
            }
        }

        return $vocabularies;
    }

    /**
     * Every call the compiler resolved to $method, a declared member — none when nothing calls it.
     *
     * @return list<NodeMatch>
     */
    public function callersOf(Node $method): array
    {
        if ($this->callers === null) {
            $this->callers = [];

            foreach ($this->whereCall()->get() as $call) {
                if ($call->node->target !== null) {
                    $this->callers[$call->node->target->symbol()][] = $call;
                }
            }
        }

        return $this->callers[(string) $method->symbol] ?? [];
    }

    /**
     * Does $call reach a method this codebase declares and may change the signature of — its own, and not one an
     * interface or a base class dictates?
     */
    public function reachesOwnSignature(Node $call): bool
    {
        return $this->declarationOf($call)->isSomeAnd(static fn (Node $method): bool => ! $method->inherited);
    }

    /**
     * Is a method named $name handed out as a delegate somewhere — named without being called (`MapPut("/items",
     * UpdateItem)`, `=> store.Persist`) — so it has callers no call site shows?
     */
    public function isHandedOut(string $name): bool
    {
        if ($this->handedOut === null) {
            $callees = array_map(static fn (NodeMatch $call): Node => $call->node->children[0], $this->whereCall()->get());
            $memberNames = array_map(static fn (NodeMatch $read): Node => $read->node->children[1], $this->whereExpression(static fn (Node $node): bool => $node->is('SimpleMemberAccessExpression'))->get());
            $named = array_flip(array_map(spl_object_id(...), [...$callees, ...$memberNames]));
            $groups = $this->whereExpression(static fn (Node $node): bool => $node->type === null && $node->is('IdentifierName', 'SimpleMemberAccessExpression') && ! isset($named[spl_object_id($node)]))->get();

            $this->handedOut = array_fill_keys(array_map(static fn (NodeMatch $group): string => (string) $group->node->referencedName(), $groups), true);
        }

        return isset($this->handedOut[$name]);
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
        return array_key_exists($symbol, $this->enums());
    }

    /**
     * The member names of the enum $symbol this codebase declares — none for any other type.
     *
     * @return list<string>
     */
    public function enumMembers(string $symbol): array
    {
        return $this->enums()[$symbol] ?? [];
    }

    /**
     * Does $switch name every member of the enum it switches over, one this codebase declares?
     */
    public function namesEveryMember(Node $switch): bool
    {
        $members = $this->enumMembers((string) $switch->children[0]->type?->name);

        return $members !== [] && array_diff($members, $switch->namedCases()) === [];
    }

    /**
     * Does $creation build a record this codebase declares with a blank string in one of its required `string`
     * slots — by position, into a `string` parameter, or by name in its initializer?
     */
    public function fillsRecordWithBlank(Node $creation): bool
    {
        $record = $this->records()[(string) $creation->type?->name] ?? null;

        if ($record === null) {
            return false;
        }

        $parameters = $creation->target->parameters ?? [];
        $byPosition = array_any($creation->blankArgumentPositions(), static fn (int $position): bool => ($parameters[$position] ?? null) === 'global::System.String');

        return $byPosition || array_intersect($creation->membersInitializedBlank(), $record->requiredTextNames()) !== [];
    }

    /**
     * The declaration of the type this codebase names $symbol — none for a type it does not declare.
     *
     * @return Option<Node>
     */
    public function typeDeclared(string $symbol): Option
    {
        $this->typeDeclarations ??= array_column(array_map(static fn (NodeMatch $type) => [(string) $type->node->symbol, $type->node], $this->whereType()->get()), 1, 0);

        return Option::fromNullable($this->typeDeclarations[$symbol] ?? null);
    }

    /**
     * Is $symbol a record this codebase declares — a value, compared by what it holds?
     */
    public function declaresRecord(string $symbol): bool
    {
        return isset($this->records()[$symbol]);
    }

    /**
     * @return array<string, Node>
     */
    private function records(): array
    {
        return $this->records ??= array_column(
            array_map(static fn (NodeMatch $record) => [(string) $record->node->symbol, $record->node], $this->whereNode(static fn (Node $node): bool => $node->isRecord())->get()),
            1,
            0,
        );
    }

    /**
     * Does this codebase declare the type $symbol — a class, record, struct, interface or enum of its own?
     */
    public function declaresType(string $symbol): bool
    {
        $this->types ??= array_flip(array_map(static fn (NodeMatch $type): string => (string) $type->node->symbol, $this->whereType()->get()));

        return isset($this->types[$symbol]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function enums(): array
    {
        if ($this->enums !== null) {
            return $this->enums;
        }

        $this->enums = [];

        foreach ($this->whereNode(static fn (Node $node): bool => $node->is('EnumDeclaration'))->get() as $enum) {
            $members = array_filter($enum->node->children, static fn (Node $child): bool => $child->is('EnumMemberDeclaration'));
            $this->enums[(string) $enum->node->symbol] = array_values(array_map(static fn (Node $member): string => (string) $member->name, $members));
        }

        return $this->enums;
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
