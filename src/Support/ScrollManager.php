<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Contracts\FileScanner;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\ProphetFailure;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\Caching\FindingsCache;
use JesseGall\CodeCommandments\Support\Caching\JudgmentCodec;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\PathExcludeMatcher;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Manages the sacred scrolls (groups of commandments).
 * Handles scanning files and judging them against commandments.
 */
class ScrollManager
{
    /** @var array<ProphetFailure> */
    protected array $failures = [];

    private ?FindingsCache $findingsCache = null;

    private bool $useCache = true;

    private ?string $prophetFilter = null;

    /**
     * Codebase indexes built this run, keyed by scroll — so the lazy builder,
     * repent's prepareCodebaseIndex, and the root-cause resolver all reuse one
     * instance per scroll instead of rebuilding (a full parse of every file).
     *
     * @var array<string, CodebaseIndex>
     */
    private array $builtIndexes = [];

    public function __construct(
        protected ProphetRegistry $registry,
        protected FileScanner $scanner,
    ) {
        ProphetExecutionContext::register();
    }

    public function setFindingsCache(?FindingsCache $cache): void
    {
        $this->findingsCache = $cache;
    }

    /**
     * Turn caching off — the pre-commit gate forces a fresh, authoritative judge.
     */
    public function setUseCache(bool $useCache): void
    {
        $this->useCache = $useCache;
    }

    /**
     * Run ONLY prophets whose short name contains $filter (partial, case-
     * insensitive — the same match `--prophet` uses for reporting). A focused
     * run does not invoke the other prophets at all, so an unrelated prophet's
     * bug can't surface; and it never touches the findings cache (its partial
     * results would poison a later full judge).
     */
    public function setProphetFilter(?string $filter): void
    {
        $this->prophetFilter = ($filter !== null && $filter !== '') ? $filter : null;
    }

    /**
     * The fully-qualified class names of the prophets that actually RUN for a
     * scroll under the current `--prophet` filter (the "active set"). The
     * root-cause resolver uses this to know which causes were filtered OUT and
     * therefore need triggering for the hint.
     *
     * @return array<class-string, true>
     */
    public function activeProphetClasses(string $scroll): array
    {
        $set = [];

        foreach ($this->prophetsFor($scroll) as $prophet) {
            $set[get_class($prophet)] = true;
        }

        return $set;
    }

    /**
     * The codebase index for a scroll, built once and memoized for the run.
     * Routing every index consumer through here guarantees a single build per
     * scroll (the parse of every file is the most expensive operation in the
     * tool).
     */
    public function codebaseIndexFor(string $scroll): CodebaseIndex
    {
        return $this->builtIndexes[$scroll] ??= $this->buildCodebaseIndex($scroll);
    }

    /**
     * The codebase index of the scroll that OWNS $filePath (the first scroll
     * whose configured path is a prefix of the file), or null when no scroll
     * claims it. Used by the root-cause resolver to give a triggered,
     * index-needing cause prophet the right cross-file view.
     */
    public function codebaseIndexForFile(string $filePath): ?CodebaseIndex
    {
        $real = realpath($filePath) ?: $filePath;

        foreach ($this->registry->getScrolls() as $scroll) {
            $config = $this->registry->getScrollConfig($scroll);
            $path = realpath($config['path'] ?? Environment::basePath());

            if ($path !== false && str_starts_with($real, $path)) {
                return $this->codebaseIndexFor($scroll);
            }
        }

        return null;
    }

    /**
     * The scroll's prophets, narrowed to the active `--prophet` filter.
     *
     * @return \Illuminate\Support\Collection<int, \JesseGall\CodeCommandments\Contracts\Commandment>
     */
    private function prophetsFor(string $scroll): Collection
    {
        $prophets = $this->registry->getProphets($scroll);

        if ($this->prophetFilter === null) {
            return $prophets;
        }

        $filter = strtolower($this->prophetFilter);

        return $prophets->filter(static fn ($prophet): bool =>
            str_contains(strtolower(class_basename(get_class($prophet))), $filter))->values();
    }

    /**
     * The cache for $scroll, activated at the current generation, or null when
     * caching is disabled. A cached entry is served ONLY when the whole scroll
     * is byte-identical and the ruleset unchanged (so a hit equals a fresh
     * judge), keeping cross-file prophets correct.
     */
    private function activateCache(string $scroll): ?FindingsCache
    {
        // A focused (`--prophet`) run produces partial findings — never read or
        // write the shared cache from it, or a later full judge serves them.
        if ($this->findingsCache === null || ! $this->useCache || $this->prophetFilter !== null) {
            return null;
        }

        $this->findingsCache->activate($scroll, $this->generation($scroll));

        return $this->findingsCache;
    }

    private function generation(string $scroll): string
    {
        return sha1($this->rulesetVersion($scroll) . '|' . $this->scrollFingerprint($scroll));
    }

    private function scrollFingerprint(string $scroll): string
    {
        $config = $this->registry->getScrollConfig($scroll);
        $path = $config['path'] ?? Environment::basePath();
        $hashes = [];

        foreach ($this->scanner->scan($path, $config['extensions'] ?? [], $config['exclude'] ?? []) as $file) {
            $real = $file->getRealPath();

            if ($real === false) {
                continue;
            }

            $content = @file_get_contents($real);

            if ($content !== false) {
                $hashes[$real] = sha1($content);
            }
        }

        ksort($hashes);

        return sha1(implode('|', array_map(static fn (string $k, string $v): string => $k . ':' . $v, array_keys($hashes), array_values($hashes))));
    }

    private function rulesetVersion(string $scroll): string
    {
        $version = class_exists(\Composer\InstalledVersions::class)
            ? (\Composer\InstalledVersions::getVersion('jessegall/code-commandments') ?? 'dev')
            : 'dev';

        $prophets = array_map('strval', $this->registry->getProphets($scroll)->map(static fn ($p): string => get_class($p))->all());

        return sha1($version . '|' . json_encode($this->registry->getScrollConfig($scroll)) . '|' . implode(',', $prophets));
    }

    /**
     * Judge one file, serving from / writing to the findings cache. The index is
     * built lazily via $ensureIndex (only on a cache miss), so a full cache hit
     * skips the index build entirely.
     *
     * @param  iterable<Commandment>  $prophets
     * @return Collection<string, Judgment>|null
     */
    private function cachedOrJudge(string $filePath, iterable $prophets, ?FindingsCache $cache, callable $ensureIndex): ?Collection
    {
        if ($cache !== null && $cache->has($filePath)) {
            $encoded = $cache->get($filePath);

            return $encoded === [] ? null : JudgmentCodec::decode($encoded);
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            return null;
        }

        $ensureIndex();

        $fileResults = collect();

        foreach ($prophets as $prophet) {
            if (! $this->isProphetApplicable($prophet, $filePath)) {
                continue;
            }

            $judgment = $this->runProphet($prophet, $filePath, $content);

            if ($judgment !== null) {
                $fileResults->put(get_class($prophet), $judgment);
            }
        }

        $cache?->put($filePath, JudgmentCodec::encode($fileResults));

        return $fileResults->isNotEmpty() ? $fileResults : null;
    }

    /**
     * @return array<ProphetFailure>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * Marker every scaffold stub carries. Files generated by this package are
     * the consumer's boilerplate, not their code — never judge them.
     */
    private const SCAFFOLD_MARKER = '@code-commandments-generated';

    /** The tool's own config file is configuration, not code — never judge it. */
    private const CONFIG_FILENAME = 'commandments.php';

    protected function runProphet(Commandment $prophet, string $filePath, string $content): ?Judgment
    {
        if (str_contains($content, self::SCAFFOLD_MARKER)) {
            return null;
        }

        // The consumer's own commandments.php is configuration, not code.
        if (basename($filePath) === self::CONFIG_FILENAME) {
            return null;
        }

        // Never let a prophet flag the very primitive it recommends: if this
        // file declares one of the prophet's configured exempt classes (its
        // Option / Union / etc.), skip it. FQCN-matched, so a domain class that
        // merely shares the short name is still judged.
        if ($this->declaresExemptClass($content, $prophet->exemptClasses())) {
            return null;
        }

        ProphetExecutionContext::enter(get_class($prophet), $filePath);

        try {
            return $prophet->judge($filePath, $content);
        } catch (Throwable $e) {
            $this->failures[] = new ProphetFailure(get_class($prophet), $filePath, $e);

            return null;
        } finally {
            ProphetExecutionContext::leave();
        }
    }

    /**
     * Whether the file declares any of the given fully-qualified class names.
     * Combines the file's `namespace` with each `class`/`interface`/`enum`/
     * `trait` declaration and matches by FQCN (case-insensitively, ignoring a
     * leading backslash), so `App\Models\Option` never matches a configured
     * `App\Support\Option`.
     *
     * @param  list<class-string>  $fqcns
     */
    protected function declaresExemptClass(string $content, array $fqcns): bool
    {
        if ($fqcns === []) {
            return false;
        }

        $namespace = '';

        if (preg_match('/^\s*namespace\s+([^;{]+)\s*[;{]/m', $content, $m) === 1) {
            $namespace = trim($m[1]);
        }

        if (preg_match_all('/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|enum|trait)\s+(\w+)/mi', $content, $matches) === 0) {
            return false;
        }

        $declared = [];

        foreach ($matches[1] as $name) {
            $fqcn = $namespace === '' ? $name : $namespace . '\\' . $name;
            $declared[strtolower(ltrim($fqcn, '\\'))] = true;
        }

        foreach ($fqcns as $fqcn) {
            if (isset($declared[strtolower(ltrim($fqcn, '\\'))])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Judge all files in a scroll.
     *
     * @return Collection<string, Collection<string, Judgment>>
     */
    public function judgeScroll(string $scroll): Collection
    {
        $config = $this->registry->getScrollConfig($scroll);
        $path = $config['path'] ?? Environment::basePath();
        $extensions = $config['extensions'] ?? [];
        $excludePaths = $config['exclude'] ?? [];

        $cache = $this->activateCache($scroll);
        $prophets = $this->prophetsFor($scroll);
        $ensureIndex = $this->lazyIndexBuilder($scroll, $prophets);

        $results = collect();

        foreach ($this->scanner->scan($path, $extensions, $excludePaths) as $file) {
            $filePath = $file->getRealPath();

            if ($filePath === false) {
                continue;
            }

            $fileResults = $this->cachedOrJudge($filePath, $prophets, $cache, $ensureIndex);

            if ($fileResults !== null) {
                $results->put($filePath, $fileResults);
            }
        }

        $cache?->save();

        return $results;
    }

    /**
     * A closure that builds + injects the codebase index exactly once, on first
     * call (a cache miss). A full cache hit never calls it, so no index is built.
     *
     * @param  iterable<Commandment>  $prophets
     */
    private function lazyIndexBuilder(string $scroll, iterable $prophets): callable
    {
        $built = false;

        return function () use (&$built, $scroll, $prophets): void {
            if (! $built) {
                $this->injectCodebaseIndex($prophets, $this->codebaseIndexFor($scroll));
                $built = true;
            }
        };
    }

    /**
     * Judge specific files in a scroll.
     *
     * @param  array<string>  $filePaths
     * @return Collection<string, Collection<string, Judgment>>
     */
    public function judgeFiles(string $scroll, array $filePaths): Collection
    {
        $config = $this->registry->getScrollConfig($scroll);
        $path = $config['path'] ?? Environment::basePath();
        $extensions = $config['extensions'] ?? [];
        $excludePaths = $config['exclude'] ?? [];

        $cache = $this->activateCache($scroll);
        $prophets = $this->prophetsFor($scroll);
        // Build the index from the FULL scroll so cross-file tracing can still
        // see callers that live outside the --git file set — but lazily, so a
        // full cache hit skips it.
        $ensureIndex = $this->lazyIndexBuilder($scroll, $prophets);

        $results = collect();

        // Normalize the scroll path for comparison
        $scrollPath = realpath($path);

        foreach ($filePaths as $filePath) {
            if (!file_exists($filePath)) {
                continue;
            }

            // Only judge files within the scroll's configured path
            if ($scrollPath !== false && !str_starts_with(realpath($filePath), $scrollPath)) {
                continue;
            }

            if (PathExcludeMatcher::shouldExclude(realpath($filePath) ?: $filePath, $excludePaths)) {
                continue;
            }

            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            if (!empty($extensions) && !in_array($extension, $extensions, true)) {
                continue;
            }

            $realFilePath = realpath($filePath) ?: $filePath;
            $fileResults = $this->cachedOrJudge($realFilePath, $prophets, $cache, $ensureIndex);

            if ($fileResults !== null) {
                $results->put($filePath, $fileResults);
            }
        }

        $cache?->save();

        return $results;
    }

    /**
     * Judge every file under a specific directory, overriding the scroll's
     * configured path AND bypassing every exclude (default + configured).
     * The caller is explicitly targeting a subtree and we trust them.
     *
     * @return Collection<string, Collection<string, Judgment>>
     */
    public function judgePath(string $scroll, string $path): Collection
    {
        $config = $this->registry->getScrollConfig($scroll);
        $extensions = $config['extensions'] ?? [];

        $prophets = $this->prophetsFor($scroll);
        // Build the index from the FULL scroll (not the narrowed path) so
        // upstream callers outside the targeted subtree still resolve.
        $this->injectCodebaseIndex($prophets, $this->codebaseIndexFor($scroll));

        $results = collect();

        foreach ($this->scanner->scan($path, $extensions, [], honorDefaultExcludes: false) as $file) {
            $filePath = $file->getRealPath();

            if ($filePath === false) {
                continue;
            }

            $content = file_get_contents($filePath);

            if ($content === false) {
                continue;
            }

            $fileResults = collect();

            foreach ($prophets as $prophet) {
                if (!$this->isProphetApplicable($prophet, $filePath)) {
                    continue;
                }

                $judgment = $this->runProphet($prophet, $filePath, $content);

                if ($judgment !== null) {
                    $fileResults->put(get_class($prophet), $judgment);
                }
            }

            if ($fileResults->isNotEmpty()) {
                $results->put($filePath, $fileResults);
            }
        }

        return $results;
    }

    /**
     * Judge a specific file against all applicable prophets in a scroll.
     *
     * The `--file` flag narrows WHICH file gets reported on, not what the
     * cross-file prophets are allowed to see: the codebase index is still
     * built from the FULL scroll so `NeedsCodebaseIndex` prophets (reused
     * enum-case groups, closed-set call sites, origin tracing) can judge
     * this one file against the whole project. Anything that lives outside
     * the targeted file simply isn't reported, but it still informs the
     * verdict on the file that is.
     *
     * @return Collection<string, Judgment>
     */
    public function judgeFile(string $scroll, string $filePath): Collection
    {
        $config = $this->registry->getScrollConfig($scroll);
        $excludePaths = $config['exclude'] ?? [];
        $resolvedPath = realpath($filePath) ?: $filePath;

        if (PathExcludeMatcher::shouldExclude($resolvedPath, $excludePaths)) {
            return collect();
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            return collect();
        }

        $prophets = $this->prophetsFor($scroll);
        $this->injectCodebaseIndex($prophets, $this->codebaseIndexFor($scroll));

        $results = collect();

        foreach ($prophets as $prophet) {
            if (!$this->isProphetApplicable($prophet, $filePath)) {
                continue;
            }

            $judgment = $this->runProphet($prophet, $filePath, $content);

            if ($judgment !== null) {
                $results->put(get_class($prophet), $judgment);
            }
        }

        return $results;
    }

    /**
     * Judge all scrolls.
     *
     * @return Collection<string, Collection<string, Collection<string, Judgment>>>
     */
    public function judgeAllScrolls(): Collection
    {
        return collect($this->registry->getScrolls())
            ->mapWithKeys(fn (string $scroll) => [$scroll => $this->judgeScroll($scroll)]);
    }

    /**
     * Get summary statistics for a scroll judgment.
     *
     * @param Collection<string, Collection<string, Judgment>> $results
     * @return array{files: int, sins: int, warnings: int, righteous: int, fallen: int}
     */
    public function getSummary(Collection $results): array
    {
        $summary = [
            'files' => $results->count(),
            'sins' => 0,
            'warnings' => 0,
            'righteous' => 0,
            'fallen' => 0,
        ];

        foreach ($results as $fileResults) {
            $fileSins = 0;
            $fileWarnings = 0;

            foreach ($fileResults as $judgment) {
                $fileSins += $judgment->sinCount();
                $fileWarnings += $judgment->warningCount();
            }

            $summary['sins'] += $fileSins;
            $summary['warnings'] += $fileWarnings;

            if ($fileSins > 0) {
                $summary['fallen']++;
            } else {
                $summary['righteous']++;
            }
        }

        return $summary;
    }

    /**
     * Check if a prophet is applicable to a file.
     */
    protected function isProphetApplicable(Commandment $prophet, string $filePath): bool
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $applicableExtensions = $prophet->applicableExtensions();

        if (!empty($applicableExtensions) && !in_array($extension, $applicableExtensions, true)) {
            return false;
        }

        // Check prophet-specific exclusions
        foreach ($prophet->getExcludedPaths() as $excludePath) {
            if (str_contains($filePath, $excludePath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get files that need judgment in a scroll.
     *
     * @return iterable<\SplFileInfo>
     */
    public function getFilesForScroll(string $scroll): iterable
    {
        $config = $this->registry->getScrollConfig($scroll);
        $path = $config['path'] ?? Environment::basePath();
        $extensions = $config['extensions'] ?? [];
        $excludePaths = $config['exclude'] ?? [];

        return $this->scanner->scan($path, $extensions, $excludePaths);
    }

    /**
     * Build a codebase index for every PHP file in a scroll.
     */
    /**
     * Build the full-scroll codebase index and inject it into every
     * `NeedsCodebaseIndex` prophet in $prophets. Repent runs prophets directly
     * (not via judgeScroll), so it must call this for cross-file auto-fixes
     * (e.g. ExplicitDataFactory's factory synthesis) to resolve.
     *
     * @param  iterable<Commandment>  $prophets
     */
    public function prepareCodebaseIndex(string $scroll, iterable $prophets): void
    {
        $this->injectCodebaseIndex($prophets, $this->codebaseIndexFor($scroll));
    }

    protected function buildCodebaseIndex(string $scroll): CodebaseIndex
    {
        $config = $this->registry->getScrollConfig($scroll);
        $path = $config['path'] ?? Environment::basePath();
        $extensions = $config['extensions'] ?? [];
        $excludePaths = $config['exclude'] ?? [];

        if (! in_array('php', $extensions, true)) {
            return CodebaseIndex::build([]);
        }

        $files = [];

        foreach ($this->scanner->scan($path, ['php'], $excludePaths) as $file) {
            $real = $file->getRealPath();

            if ($real !== false) {
                $files[] = $real;
            }
        }

        return CodebaseIndex::build($files);
    }

    /**
     * Inject the codebase index into every prophet that implements
     * `NeedsCodebaseIndex`.
     *
     * @param  iterable<Commandment>  $prophets
     */
    protected function injectCodebaseIndex(iterable $prophets, CodebaseIndex $index): void
    {
        foreach ($prophets as $prophet) {
            if ($prophet instanceof NeedsCodebaseIndex) {
                $prophet->setCodebaseIndex($index);
            }
        }
    }
}
