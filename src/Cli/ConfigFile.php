<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use RuntimeException;

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
    public const string TEMPLATE = <<<'PHP'
        <?php

        declare(strict_types=1);

        use JesseGall\CodeCommandments\Config;

        /*
         | code-commandments configuration — OPTIONAL.
         |
         | Every detector is enabled by default with sensible thresholds, so you don't have to
         | touch this file. Turn rules off with `commandments disable/enable <sin>`, or by hand
         | inside the disable() call below. See the README "Configuration" section.
         */

        return function (Config $config): void {
            // Silence rules — a Sin, a Detector, or a whole Skill class:
            $config->disable(
                // \JesseGall\CodeCommandments\Sins\Backend\SwallowCatch::class,
            );

            // Add your own:
            //   $config->detector(\App\Commandments\NoRawSqlDetector::class);
            //   $config->package(\App\Commandments\MyFrameworkPackage::class);
            //   $config->configure(fn (\JesseGall\CodeCommandments\Detectors\Frontend\DeepNestedDetector $d) => $d->maxDepth(10));
        };

        PHP;

    public function __construct(public readonly string $path) {}

    /**
     * The config file for a project — `<dir>/.commandments/config.php` (default the cwd).
     */
    public static function inProject(?string $dir = null): self
    {
        return new self(($dir ?? getcwd()) . '/.commandments/config.php');
    }

    /**
     * Write the scaffold ONCE, if the file isn't there yet. Returns true when it created one.
     */
    public function scaffoldIfMissing(): bool
    {
        if (is_file($this->path)) {
            return false;
        }

        @mkdir(dirname($this->path), 0777, true);
        file_put_contents($this->path, self::TEMPLATE);

        return true;
    }

    /**
     * The sin/detector classes the config currently disables — read from the `disable()` call's
     * `::class` arguments via the AST.
     *
     * @return list<string>
     */
    public function disabled(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $classes = [];

        foreach (Codebase::fromString((string) file_get_contents($this->path), $this->path)->whereMethod('disable')->get() as $match) {
            $call = $match->node;

            if ($call instanceof MethodCall) {
                $classes = [...$classes, ...self::argClasses($call)];
            }
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
     * The `$config->disable(...)` call node in the config. Throws when the file has none —
     * the scaffold always ships one, so this only trips on a file edited past recognition.
     */
    private function disableCall(): MethodCall
    {
        $match = Codebase::fromString((string) file_get_contents($this->path), $this->path)->whereMethod('disable')->first();

        if (! $match?->node instanceof MethodCall) {
            throw new RuntimeException("{$this->path} has no `\$config->disable(...)` call to manage — restore it, or delete the file to regenerate.");
        }

        return $match->node;
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
