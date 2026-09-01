<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Config;

use JesseGall\CodeCommandments\Workspace;

use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use JesseGall\CodeCommandments\Span;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;

/**
 * The project's `.commandments/config.php` as an editable thing — it owns the scaffold template
 * and the read/write of the `$config->disable(…)` call that `commandments disable`/`enable`
 * manage. It NEVER scans the file as text: the config is PHP, so it's parsed with our own AST
 * engine ({@see Codebase::whereMethod}), and the one call's arguments are rewritten by their
 * node offsets — the same way a Scribe edits. The human's own `register`/`configure` lines are
 * never touched.
 */
final class ConfigFile
{
    public function __construct(public readonly string $path) {}

    /**
     * The config file for a project — `<dir>/.commandments/config.php` (default the cwd).
     */
    public static function inProject(?string $dir = null): self
    {
        return new self(Workspace::config($dir));
    }

    /**
     * Write the scaffold ONCE, if the file isn't there yet — with the source roots already detected
     * (composer PSR-4 + app/src), so a freshly-synced config declares its `paths()` immediately
     * instead of waiting for the first judge to fill them. Returns true when it created one. The
     * write itself is the {@see ConfigScribe}'s job.
     */
    public function scaffoldIfMissing(): bool
    {
        $root = dirname($this->path, 2); // <root>/.commandments/config.php → <root>

        return new ConfigScribe($this->path)->scaffold(new SourceRoots()->detect($root));
    }

    /**
     * The source roots the config declares — read from the `paths()` call's string arguments.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        $call = $this->calls()->named('paths');

        return $call === null ? [] : self::argStrings($call);
    }

    /**
     * The sin/detector classes the config currently disables — read from the `disable()` call's
     * `::class` arguments via the AST.
     *
     * @return list<string>
     */
    public function disabled(): array
    {
        $classes = [];

        foreach ($this->calls()->all('disable') as $call) {
            $classes = [...$classes, ...self::argClasses($call)];
        }

        return array_values(array_unique($classes));
    }

    /**
     * Add `$fqcn::class` to the `disable()` call (scaffolding the file first). False if already
     * disabled.
     */
    public function disable(string $fqcn): bool
    {
        $this->scaffoldIfMissing();
        $fqcn = ltrim($fqcn, '\\');
        $call = $this->disableCall();
        $current = self::argClasses($call);

        if (in_array($fqcn, $current, true)) {
            return false;
        }

        $this->rewriteArgs($call, [...$current, $fqcn]);

        return true;
    }

    /**
     * Remove `$fqcn::class` from the `disable()` call. False if it wasn't disabled.
     */
    public function enable(string $fqcn): bool
    {
        if (! is_file($this->path)) {
            return false;
        }

        $fqcn = ltrim($fqcn, '\\');
        $call = $this->disableCall();
        $remaining = array_values(array_filter(self::argClasses($call), static fn (string $c): bool => $c !== $fqcn));

        if (count($remaining) === count(self::argClasses($call))) {
            return false;
        }

        $this->rewriteArgs($call, $remaining);

        return true;
    }

    /**
     * The detector classes the config registers — read from the `detector()` call's `::class`
     * arguments, the twin of {@see disabled}.
     *
     * @return list<string>
     */
    public function detectors(): array
    {
        $call = $this->calls()->named('detector');

        return $call === null ? [] : self::argClasses($call);
    }

    /**
     * Register a project's own detector — `$config->detector(\Foo\BarDetector::class)`. Adds the
     * class to the existing `detector()` call, or writes a whole new statement after `paths()`
     * when the config has none yet (the scaffold ships only a commented-out example). False when
     * that detector is already registered. Like every edit here it goes through the AST: the new
     * argument is spliced by node offset and the statement takes the indentation of the one it
     * follows, so the human's formatting survives.
     */
    public function registerDetector(string $fqcn): bool
    {
        $this->scaffoldIfMissing();
        $fqcn = ltrim($fqcn, '\\');
        $call = $this->calls()->named('detector');

        if ($call !== null) {
            $current = self::argClasses($call);

            if (in_array($fqcn, $current, true)) {
                return false;
            }

            $this->rewriteArgs($call, [...$current, $fqcn]);

            return true;
        }

        $this->appendStatement("\$config->detector(\\{$fqcn}::class);");

        return true;
    }

    /**
     * Write a new statement into the config's composer closure, on its own line after the LAST
     * statement already there, at that statement's own indentation. Falls back to the `paths()`
     * statement's line when the file has been edited past recognition.
     */
    private function appendStatement(string $statement): void
    {
        $anchor = $this->lastStatement();

        if ($anchor === null) {
            throw UnrecognizableConfig::at($this->path);
        }

        $source = (string) file_get_contents($this->path);
        $at = $anchor->getEndFilePos() + 1;
        $indent = Span::indentAt($source, $anchor->getStartFilePos());

        file_put_contents($this->path, substr($source, 0, $at) . "\n\n{$indent}{$statement}" . substr($source, $at));
    }

    /**
     * The last `$config->…;` statement in the config — the line a new declaration is written after.
     */
    private function lastStatement(): ?Expression
    {
        $statements = $this->calls()->statements();

        return $statements === [] ? null : $statements[count($statements) - 1];
    }

    private function calls(): ConfigCalls
    {
        return new ConfigCalls($this->path);
    }

    /**
     * The layers the config declares — `->layer('A', mayUse: ['B'])` read off the chain, in the order
     * they stand, each with the layers it may use. Empty when nothing is declared.
     *
     * @return array<string, list<string>>  layer namespace => the namespaces it may use
     */
    public function layers(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $layers = [];

        // Outermost first is the LAST link written, so read the chain back to front to get the
        // order the human sees.
        foreach (array_reverse($this->layerCalls()) as $call) {
            $namespace = $call->args[0]->value ?? null;

            if ($namespace instanceof String_) {
                $layers[$namespace->value] = self::mayUseOf($call);
            }
        }

        return $layers;
    }

    /**
     * Rewrite the declared layers in place — the `->layer(...)` CHAIN replaced, with everything around
     * it (the `configure(...)` wrapper, its formatting, the rest of the file) exactly as it was. The
     * new chain takes the indentation the old one had. False when the config declares no layers yet.
     *
     * @param  array<string, list<string>>  $layers
     */
    public function rewriteLayers(array $layers): bool
    {
        $calls = $this->layerCalls();

        if ($calls === []) {
            return false;
        }

        $source = (string) file_get_contents($this->path);

        // The outermost call is the LAST link written, so the chain runs from the innermost link's
        // receiver to the outermost's closing paren.
        $from = self::chainStart($calls[count($calls) - 1]);
        $to = $calls[0]->getEndFilePos() + 1;

        $chain = self::renderChain($layers, self::indentOfChain($source, $from));

        file_put_contents($this->path, substr($source, 0, $from) . $chain . substr($source, $to));

        return true;
    }

    /**
     * The `->layer(...)` chain as source, one link per line at $indent — the ONE renderer, shared by
     * the first write and every incremental edit, so a config never gains a second house style.
     *
     * @param  array<string, list<string>>  $layers
     */
    public static function renderChain(array $layers, string $indent): string
    {
        $links = [];

        foreach ($layers as $namespace => $uses) {
            $may = $uses === [] ? '' : ', mayUse: [' . implode(', ', array_map(self::quoted(...), $uses)) . ']';
            $links[] = "\n{$indent}->layer(" . self::quoted((string) $namespace) . $may . ')';
        }

        return implode('', $links);
    }

    /**
     * The indentation the existing chain's links stand at — read from the first line after $from, so a
     * rewritten chain lines up with whatever the project (or its formatter) settled on.
     */
    private static function indentOfChain(string $source, int $from): string
    {
        $rest = substr($source, $from);
        $lines = preg_split('/\R/', $rest) ?: [];

        foreach ($lines as $line) {
            if (trim($line) !== '') {
                return substr($line, 0, strlen($line) - strlen(ltrim($line)));
            }
        }

        return '        ';
    }

    private static function quoted(string $namespace): string
    {
        return "'" . str_replace('\\', '\\\\', $namespace) . "'";
    }

    /**
     * Every `->layer(...)` call in the config, outermost first (which is the LAST link written).
     *
     * @return list<MethodCall>
     */
    private function layerCalls(): array
    {
        return $this->calls()->all('layer');
    }

    /**
     * Where the chain this call belongs to begins — walk left through the `->layer(...)` links to the
     * receiver they all hang off (`$detector`), and take the offset just after it.
     */
    private static function chainStart(MethodCall $call): int
    {
        $receiver = $call->var;

        return $receiver->getEndFilePos() + 1;
    }

    /**
     * The `mayUse:` argument of a `->layer(...)` call, as namespaces.
     *
     * @return list<string>
     */
    private static function mayUseOf(MethodCall $call): array
    {
        foreach ($call->args as $arg) {
            if (! $arg->value instanceof Array_) {
                continue;
            }

            $uses = [];

            foreach ($arg->value->items as $item) {
                if ($item?->value instanceof String_) {
                    $uses[] = $item->value->value;
                }
            }

            return $uses;
        }

        return [];
    }

    /**
     * The `$config->disable(...)` call node in the config. Throws when the file has none —
     * the scaffold always ships one, so this only trips on a file edited past recognition.
     */
    private function disableCall(): MethodCall
    {
        return $this->calls()->require('disable');
    }

    /**
     * The fully-qualified class names in a `disable(A::class, B::class)` call.
     *
     * @return list<string>
     */
    private static function argClasses(MethodCall $call): array
    {
        $classes = [];

        foreach ($call->args as $arg) {
            if ($arg->value instanceof ClassConstFetch && $arg->value->name->toString() === 'class') {
                $classes[] = ltrim($arg->value->class->toString(), '\\');
            }
        }

        return $classes;
    }

    /**
     * The string-literal arguments in a `paths('app', 'src')` call.
     *
     * @return list<string>
     */
    private static function argStrings(MethodCall $call): array
    {
        $strings = [];

        foreach ($call->args as $arg) {
            if ($arg->value instanceof String_) {
                $strings[] = $arg->value->value;
            }
        }

        return $strings;
    }

    /**
     * Replace the argument list of $call with the given classes, spliced by node offset — the
     * parens (and everything around the call) are left exactly as they were.
     *
     * @param  list<string>  $classes
     */
    private function rewriteArgs(MethodCall $call, array $classes): void
    {
        $source = (string) file_get_contents($this->path);
        $rendered = implode(', ', array_map(static fn (string $c): string => "\\{$c}::class", $classes));

        if ($call->args !== []) {
            $from = $call->args[0]->value->getStartFilePos();
            $to = end($call->args)->value->getEndFilePos() + 1;
        } else {
            $from = $to = $call->getEndFilePos(); // between the empty `()`
        }

        file_put_contents($this->path, substr($source, 0, $from) . $rendered . substr($source, $to));
    }
}
