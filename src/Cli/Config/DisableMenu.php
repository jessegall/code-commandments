<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Config;

use JesseGall\PhpTypes\Option;

use JesseGall\CodeCommandments\Workspace;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Agents\Catalog as Agents;
use JesseGall\CodeCommandments\Hooks\HookRegistry;
use JesseGall\CodeCommandments\Sins\Catalog as Sins;
use JesseGall\CodeCommandments\Skills\Catalog as Skills;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;

/**
 * The disable menus in `.commandments/config.php` — a `$disabledSkills`, a `$disabledSins`, and a
 * `$disabledHooks` closure, each one variadic `$config->disable(...)` call listing every shipped
 * skill/sin/hook as a commented-out argument. Uncommenting a line disables that rule (or, for the
 * hooks menu, that Claude Code nudge). `sync` re-ensures the menus on every composer update: missing
 * entries are added commented AND every rule entry is regrouped under the right `Backend`/`Frontend`
 * divider (self-healing — a rule that drifted into the wrong section is moved back); the hooks menu
 * is a flat list, ungrouped, since a hook has no engine. An UNCOMMENTED entry — the human's active
 * choice — is never removed, only regrouped; a menu carrying any unrecognized custom line is left to
 * plain append, untouched. Like every scribe it edits through the AST, splicing by node offset.
 */
final class DisableMenu
{
    private const string SKILLS_VAR = 'disabledSkills';

    private const string SINS_VAR = 'disabledSins';

    private const string HOOKS_VAR = 'disabledHooks';

    private const string AGENTS_VAR = 'disabledAgents';

    private const string PACKAGE_NS = 'JesseGall\\CodeCommandments\\';

    private const string BACKEND_SEPARATOR = '// ----------[ Backend ]----------';

    private const string FRONTEND_SEPARATOR = '// ----------[ Frontend ]----------';

    private const string SUFFIX = '::class,';

    public function __construct(public readonly string $path) {}

    /**
     * The disable menus for a project — `<dir>/.commandments/config.php` (default the cwd).
     */
    public static function inProject(?string $dir = null): self
    {
        return new self(Workspace::config($dir));
    }

    /**
     * Ensure both menus exist and list every shipped skill and sin. Idempotent; a config edited
     * past recognition (no `return function` closure) is left untouched.
     */
    public function ensure(): void
    {
        if (! is_file($this->path) || ! $this->recognizable()) {
            return;
        }

        foreach ($this->menus() as $menu) {
            $this->ensureImport($menu['import']);
        }

        foreach ($this->menus() as $menu) {
            $this->ensureDefinition($menu['var'], $menu['purpose'], $menu['refs'], $menu['grouped']);
        }

        $this->ensureUseClause();
        $this->ensureCalls();

        foreach ($this->menus() as $menu) {
            $this->reconcile($menu['var'], $menu['refs'], $menu['grouped']);
        }
    }

    // ----------[ Menu contents ]----------

    /**
     * The menus this manages, each self-describing: its closure variable, the namespace it needs
     * imported, its one-line purpose, the canonical references it lists, and whether those group by
     * engine. Rules (skills, sins) group Backend/Frontend; hooks and agents are engine-agnostic, so
     * their menus are flat lists.
     *
     * EVERY pass reads this one list — the import, the definition, the `use (…)` clause, the call
     * and the reconcile. A menu declared here but missing from one of those passes would be written
     * into the config and never invoked: a silently dead switch, which `php -l` cannot see.
     *
     * @return list<array{var: string, import: string, purpose: string, refs: list<string>, grouped: bool}>
     */
    private function menus(): array
    {
        return [
            ['var' => self::SKILLS_VAR, 'import' => 'Skills', 'purpose' => 'Uncomment a line to disable that skill (every detector it teaches).', 'refs' => $this->skillRefs(), 'grouped' => true],
            ['var' => self::SINS_VAR, 'import' => 'Sins', 'purpose' => 'Uncomment a line to disable that single sin.', 'refs' => $this->sinRefs(), 'grouped' => true],
            ['var' => self::HOOKS_VAR, 'import' => 'Hooks', 'purpose' => 'Uncomment a line to disable that Claude Code hook (a wired nudge).', 'refs' => $this->hookRefs(), 'grouped' => false],
            ['var' => self::AGENTS_VAR, 'import' => 'Agents', 'purpose' => 'Uncomment a line to stop publishing into that agent (its skills, its instructions file, its hooks).', 'refs' => $this->agentRefs(), 'grouped' => false],
        ];
    }

    /**
     * Every shipped agent as a package-relative class reference.
     *
     * @return list<string>
     */
    private function agentRefs(): array
    {
        return $this->refs(array_map(static fn (object $agent): string => $agent::class, Agents::all()));
    }

    /**
     * Every wired Claude Code hook as a package-relative class reference, sorted.
     *
     * @return list<string>
     */
    private function hookRefs(): array
    {
        return $this->refs(HookRegistry::BUILTINS);
    }

    /**
     * Every shipped skill as a package-relative class reference, backend first, sorted.
     *
     * @return list<string>
     */
    private function skillRefs(): array
    {
        return $this->refs(array_map(static fn (object $skill): string => $skill::class, Skills::all()));
    }

    /**
     * Every shipped sin as a package-relative class reference, backend first, sorted.
     *
     * @return list<string>
     */
    private function sinRefs(): array
    {
        return $this->refs(array_map(static fn (object $sin): string => $sin::class, Sins::every()));
    }

    /**
     * Strip the package namespace and sort, backend before frontend.
     *
     * @param  list<class-string>  $classes
     * @return list<string>
     */
    private function refs(array $classes): array
    {
        $refs = array_values(array_unique(array_map(
            static fn (string $class): string => str_starts_with($class, self::PACKAGE_NS) ? substr($class, strlen(self::PACKAGE_NS)) : '\\' . $class,
            $classes,
        )));

        usort($refs, static fn (string $a, string $b): int => [str_contains($a, '\\Frontend\\'), $a] <=> [str_contains($b, '\\Frontend\\'), $b]);

        return $refs;
    }

    // ----------[ Ensure passes ]----------

    /**
     * Add `use JesseGall\CodeCommandments\<Skills|Sins>;` after the last import when missing.
     */
    private function ensureImport(string $group): void
    {
        $imports = $this->parse()->where(static fn (AstNode $n): bool => $n->node instanceof Use_)->get();

        if ($imports === []) {
            return;
        }

        $target = self::PACKAGE_NS . $group;
        $last = null;

        foreach ($imports as $import) {
            /**
             * @var Use_ $node
             */
            $node = $import->node;

            foreach ($node->uses as $use) {
                if ($use->name->toString() === $target) {
                    return;
                }
            }

            $last = $node;
        }

        $this->splice($last->getEndFilePos() + 1, "\nuse {$target};");
    }

    /**
     * Splice the `$<var> = function (Config $config): void { … };` menu before the `return`
     * closure when the config doesn't define it yet.
     *
     * @param  list<string>  $refs
     */
    private function ensureDefinition(string $var, string $purpose, array $refs, bool $grouped): void
    {
        if ($this->hasMenu($var)) {
            return;
        }

        $entries = implode("\n", array_map(
            static fn (string $entry): string => $entry === '' ? '' : "        {$entry}",
            $this->entries($refs, $grouped),
        ));

        $definition = <<<PHP
            /**
             * {$purpose} New entries are appended on composer update; your edits are kept.
             */
            \${$var} = function (Config \$config): void {
                \$config->disable(
            {$entries}
                );
            };
            PHP;

        $this->splice($this->returnStatement()->getStartFilePos(), $definition . "\n\n");
    }

    /**
     * The menu's argument lines — every reference commented out. A grouped menu splits by an engine
     * divider; an ungrouped one (the hooks menu) is a flat, already-sorted list.
     *
     * @param  list<string>  $refs
     * @return list<string>
     */
    private function entries(array $refs, bool $grouped): array
    {
        if (! $grouped) {
            return array_map(static fn (string $ref): string => "// {$ref}::class,", $refs);
        }

        $backend = [];
        $frontend = [];

        foreach ($refs as $ref) {
            $line = "// {$ref}::class,";

            if (str_contains($ref, '\\Frontend\\')) {
                $frontend[] = $line;
            } else {
                $backend[] = $line;
            }
        }

        $lines = [self::BACKEND_SEPARATOR, ...$backend];

        if ($frontend !== []) {
            $lines[] = '';
            $lines[] = self::FRONTEND_SEPARATOR;
            $lines = [...$lines, ...$frontend];
        }

        return $lines;
    }

    /**
     * Carry both menu variables on the `return` closure's `use (…)` clause.
     */
    private function ensureUseClause(): void
    {
        foreach (array_column($this->menus(), 'var') as $var) {
            $closure = $this->returnClosure();

            if ($this->carries($closure, $var)) {
                continue;
            }

            if ($closure->uses !== []) {
                $this->splice(end($closure->uses)->getEndFilePos() + 1, ", \${$var}");

                continue;
            }

            $at = $this->paramsCloseParen($closure);

            if ($at !== null) {
                $this->splice($at + 1, " use (\${$var})");
            }
        }
    }

    /**
     * Call both menus at the end of the `return` closure's body.
     */
    private function ensureCalls(): void
    {
        foreach (array_column($this->menus(), 'var') as $var) {
            $closure = $this->returnClosure();
            $body = substr($this->source(), $closure->getStartFilePos(), $closure->getEndFilePos() - $closure->getStartFilePos());

            if (str_contains($body, "\${$var}(\$config)")) {
                continue;
            }

            $this->splice($closure->getEndFilePos(), "    \${$var}(\$config);\n");
        }
    }

    /**
     * Rebuild the menu's `disable(…)` argument list: add any canonical reference it lacks (commented),
     * regroup every entry under the right Backend/Frontend divider, and PRESERVE each entry's
     * commented/uncommented state (an uncommented rule the human disabled is kept, just moved to its
     * group). A menu carrying an unrecognized custom line is not rebuilt — it falls back to a safe
     * end-append, so hand-authored content is never dropped.
     *
     * @param  list<string>  $canonical
     */
    private function reconcile(string $var, array $canonical, bool $grouped): void
    {
        $call = $this->menuDisableCall($var)
            ->filter(static fn (MethodCall $c): bool => $c->name instanceof \PhpParser\Node\Identifier);

        if ($call->isNone()) {
            return;
        }

        $call = $call->unwrap();

        $source = $this->source();
        $open = strpos($source, '(', $call->name->getEndFilePos());
        $close = $call->getEndFilePos();

        if ($open === false || $close <= $open) {
            return;
        }

        $entries = $this->parseEntries(substr($source, $open + 1, $close - $open - 1));

        if ($entries === null) {
            $this->appendMissing($var, $canonical); // custom content present — don't rebuild

            return;
        }

        foreach ($canonical as $ref) {
            $entries[$ref] ??= true; // a canonical rule the menu lacks, added commented
        }

        $this->replaceRange($open + 1, $close, "\n" . implode("\n", $this->groupedLines($entries, $grouped)) . "\n    ");
    }

    /**
     * The refs a `disable(…)` body already lists, mapped ref => commented?. Null when the body carries a
     * line that is neither a `Ref::class,` entry, a divider, nor blank — a signal to leave it be.
     *
     * @return array<string, bool>|null
     */
    private function parseEntries(string $body): ?array
    {
        $entries = [];

        foreach (explode("\n", $body) as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_contains($trimmed, '----------[')) {
                continue; // blank or a divider — regenerated
            }

            $commented = str_starts_with($trimmed, '//');
            $ref = $commented ? ltrim(substr($trimmed, 2)) : $trimmed;

            if (! str_ends_with($ref, self::SUFFIX)) {
                return null; // an unrecognized custom line — bail out of rebuilding
            }

            $ref = substr($ref, 0, -strlen(self::SUFFIX));

            // An uncommented occurrence wins — never silently re-comment a rule the human disabled.
            $entries[$ref] = ($entries[$ref] ?? true) && $commented;
        }

        return $entries;
    }

    /**
     * The indented argument lines for a `disable(…)` body, each carrying its preserved commented
     * state. A grouped menu emits Backend entries, a blank line, then Frontend entries (sorted within
     * each group); an ungrouped menu (hooks) emits one flat, sorted list.
     *
     * @param  array<string, bool>  $entries  ref => commented?
     * @return list<string>
     */
    private function groupedLines(array $entries, bool $grouped): array
    {
        if (! $grouped) {
            ksort($entries);

            return array_values(array_map(
                static fn (bool $commented, string $ref): string => '        ' . ($commented ? '// ' : '') . $ref . self::SUFFIX,
                $entries,
                array_keys($entries),
            ));
        }

        $backend = [];
        $frontend = [];

        foreach ($entries as $ref => $commented) {
            $line = '        ' . ($commented ? '// ' : '') . $ref . self::SUFFIX;

            if (str_contains($ref, '\\Frontend\\')) {
                $frontend[$ref] = $line;
            } else {
                $backend[$ref] = $line;
            }
        }

        ksort($backend);
        ksort($frontend);

        $lines = ['        ' . self::BACKEND_SEPARATOR, ...array_values($backend)];

        if ($frontend !== []) {
            $lines[] = '';
            $lines[] = '        ' . self::FRONTEND_SEPARATOR;
            $lines = [...$lines, ...array_values($frontend)];
        }

        return $lines;
    }

    /**
     * The safe fallback for a hand-edited menu: append every canonical reference it doesn't mention yet,
     * commented, at the end — never rewriting what's there.
     *
     * @param  list<string>  $canonical
     */
    private function appendMissing(string $var, array $canonical): void
    {
        foreach ($canonical as $ref) {
            $found = $this->menuDisableCall($var);

            if ($found->isNone()) {
                return;
            }

            $call = $found->unwrap();

            $slice = substr($this->source(), $call->getStartFilePos(), $call->getEndFilePos() - $call->getStartFilePos());

            if (str_contains($slice, "{$ref}::class") || str_contains($slice, self::PACKAGE_NS . ltrim($ref, '\\') . '::class')) {
                continue;
            }

            $this->splice($call->getEndFilePos(), "    // {$ref}::class,\n    ");
        }
    }

    // ----------[ AST lookups ]----------

    private function parse(): Codebase
    {
        return Codebase::fromString($this->source(), $this->path);
    }

    private function source(): string
    {
        return (string) file_get_contents($this->path);
    }

    private function splice(int $at, string $text): void
    {
        $source = $this->source();

        file_put_contents($this->path, substr($source, 0, $at) . $text . substr($source, $at));
    }

    /**
     * Replace the byte range [$start, $end) with $text.
     */
    private function replaceRange(int $start, int $end, string $text): void
    {
        $source = $this->source();

        file_put_contents($this->path, substr($source, 0, $start) . $text . substr($source, $end));
    }

    /**
     * Does the config return a closure the menus can wire into?
     */
    private function recognizable(): bool
    {
        return $this->parse()->where(self::isConfigReturn(...))->first() !== null;
    }

    /**
     * The `return function (Config $config) …;` statement.
     *
     * @throws UnrecognizableConfig when the config doesn't return a closure.
     */
    private function returnStatement(): Return_
    {
        $match = $this->parse()->where(self::isConfigReturn(...))->first();

        if (! $match?->node instanceof Return_) {
            throw UnrecognizableConfig::at($this->path);
        }

        return $match->node;
    }

    /**
     * The closure the config file returns.
     *
     * @throws UnrecognizableConfig when the config doesn't return a closure.
     */
    private function returnClosure(): Closure
    {
        $closure = $this->returnStatement()->expr;

        if (! $closure instanceof Closure) {
            throw UnrecognizableConfig::at($this->path);
        }

        return $closure;
    }

    private static function isConfigReturn(AstNode $node): bool
    {
        return $node->node instanceof Return_ && $node->node->expr instanceof Closure;
    }

    /**
     * Is the `$<var> = function (…) {…};` menu defined?
     */
    private function hasMenu(string $var): bool
    {
        return $this->parse()->where(static fn (AstNode $n) => self::isMenuAssign($n, $var))->first() !== null;
    }

    /**
     * The `$<var> = function (…) {…};` assignment, or null when the menu isn't defined.
     */
    private function definition(string $var): ?Assign
    {
        $match = $this->parse()->where(static fn (AstNode $n) => self::isMenuAssign($n, $var))->first();

        return $match?->node instanceof Assign ? $match->node : null;
    }

    private static function isMenuAssign(AstNode $node, string $var): bool
    {
        return $node->node instanceof Assign
            && $node->node->var instanceof Variable
            && $node->node->var->name === $var
            && $node->node->expr instanceof Closure;
    }

    /**
     * The `$config->disable(…)` call inside the menu's closure, or null.
     *
     * @return Option<MethodCall>
     */
    private function menuDisableCall(string $var): Option
    {
        $definition = $this->definition($var);

        if ($definition === null) {
            return Option::none();
        }

        [$from, $to] = [$definition->getStartFilePos(), $definition->getEndFilePos()];

        $match = $this->parse()->whereMethod('disable')
            ->where(static fn (AstNode $n): bool => $n->node->getStartFilePos() >= $from && $n->node->getEndFilePos() <= $to)
            ->first();

        return $match?->node instanceof MethodCall ? Option::some($match->node) : Option::none();
    }

    private function carries(Closure $closure, string $var): bool
    {
        foreach ($closure->uses as $use) {
            if ($use->var instanceof Variable && $use->var->name === $var) {
                return true;
            }
        }

        return false;
    }

    /**
     * The offset of the `)` closing the closure's parameter list, or null when it can't be found.
     */
    private function paramsCloseParen(Closure $closure): ?int
    {
        $after = $closure->params === [] ? $closure->getStartFilePos() : end($closure->params)->getEndFilePos() + 1;
        $at = strpos($this->source(), ')', $after);

        return $at === false ? null : $at;
    }
}
