<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pilgrimage;

use Illuminate\Filesystem\Filesystem;
use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\GitFileDetector;
use JesseGall\CodeCommandments\Support\Profiles\JudgeScope;
use JesseGall\CodeCommandments\Support\ScrollScope;
use JesseGall\CodeCommandments\Support\RootCauseMap;
use JesseGall\CodeCommandments\Support\Profiles\ProfileService;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Tracking\JsonConfessionTracker;
use ReflectionClass;

/**
 * Drives the forward-only pilgrimage one PROPHET at a time. `begin()` resets and
 * walks to the first prophet with findings; `advance()` first RE-SCANS the current
 * prophet — if anything remains it re-shows it and refuses to move on — and only
 * once it is clean does it step to the next prophet (scanning it on arrival).
 * Clean prophets are skipped silently; a passed prophet is never re-scanned.
 */
final class PilgrimageRunner
{
    /** @var array<class-string<Commandment>, Commandment>|null */
    private ?array $prophetsByClass = null;

    /** @var list<array{name: string, pillars: list<list<class-string<Commandment>>>}>|null */
    private ?array $itinerary = null;

    private ?CodebaseIndex $index = null;

    private ?ConfessionTracker $tracker = null;

    /** The single prophet this walk is constrained to (FQCN), or null for the full cascade. */
    private ?string $onlyProphet = null;

    private readonly string $scroll;

    public function __construct(
        private readonly string $basePath,
        private readonly array $config,
        ?string $scroll = null,
    ) {
        $this->scroll = $this->resolveScroll($scroll);
    }

    public static function fromConfig(string $basePath, string $configPath, ?string $scroll = null): self
    {
        return new self($basePath, ConfigLoader::load($configPath), $scroll);
    }

    public function scroll(): string
    {
        return $this->scroll;
    }

    /**
     * The scroll to walk. An explicit name wins if it exists; otherwise pick the
     * first scroll whose extensions include `php` (the doctrines are backend rules),
     * falling back to the first scroll. Consumers name scrolls freely
     * (`acme-backend`), so a hardcoded `backend` would silently register no prophets.
     */
    private function resolveScroll(?string $requested): string
    {
        $scrolls = $this->config['scrolls'] ?? [];

        if ($requested !== null && isset($scrolls[$requested])) {
            return $requested;
        }

        foreach ($scrolls as $name => $scroll) {
            if (in_array('php', $scroll['extensions'] ?? [], true)) {
                return (string) $name;
            }
        }

        return (string) (array_key_first($scrolls) ?? ($requested ?? 'backend'));
    }

    /**
     * Peek at the CURRENT prophet without advancing: re-scan it and return its
     * remaining locations (the agent's live "what's still to fix here" list).
     * Returns null when no walk is in progress; an empty `locations` means the
     * current prophet is clean and `next` would advance.
     *
     * @return array<string, mixed>|null
     */
    public function peek(): ?array
    {
        $state = PilgrimageState::load($this->basePath);

        if ($state === null) {
            return null;
        }

        if ($state->complete) {
            return ['complete' => true, 'locations' => []];
        }

        $prophet = $this->currentProphet($state);

        if ($prophet === null) {
            return ['complete' => false, 'prophet' => null, 'locations' => []];
        }

        return [
            'complete' => false,
            'prophet' => (new ReflectionClass($prophet))->getShortName(),
            'auto_fixable' => $prophet instanceof \JesseGall\CodeCommandments\Contracts\SinRepenter,
            'doctrine' => $this->itinerary()[$state->doctrine]['name'] ?? 'singletons',
            'locations' => $this->scan($prophet, $state),
        ];
    }

    /**
     * Reset the walk and stop at the first prophet that has findings. The walk's
     * SCOPE is frozen from the active profile: which files (whole scroll / branch /
     * staged) and severity, plus an optional single-prophet constraint. Refuses to
     * clobber an in-flight walk owned by this session (finish with `next` or leave
     * with `abandon` first) — a completed walk restarts freely.
     *
     * @param  class-string<Commandment>|null  $onlyProphet  constrain to one prophet
     * @return array<string, mixed>
     */
    public function begin(?string $onlyProphet = null): array
    {
        $existing = PilgrimageState::load($this->basePath);

        if ($existing !== null && ! $existing->complete
            && $existing->owner !== '' && $existing->owner === PilgrimageState::currentSession()) {
            return ['error' => true, 'inProgress' => $existing->onlyProphet];
        }

        $this->onlyProphet = $onlyProphet;

        PilgrimageState::clear($this->basePath);
        PilgrimageIndexCache::clear($this->basePath);

        // A fresh reckoning: clear ordinary finding-absolutions so everything
        // re-surfaces (reported + until-push absolutions are deliberately kept —
        // a reported finding stays quiet until its issue is answered).
        $this->tracker()->clearFindingAbsolutions();

        $opts = ProfileService::resolve($this->basePath)->options();
        $scopeKind = $this->scopeKindFor($opts->scope);

        $state = new PilgrimageState(
            scope: $this->filesForScopeKind($scopeKind),
            scroll: $this->scroll,
            owner: PilgrimageState::currentSession(),
            scopeKind: $scopeKind,
            allowWarnings: $opts->allowWarnings,
            onlyProphet: $onlyProphet,
        );

        $step = $this->walkFrom($state);
        $state->save($this->basePath);

        return $step;
    }

    /** The profile's JudgeScope as the persisted scope-kind tag. */
    private function scopeKindFor(JudgeScope $scope): string
    {
        return match (true) {
            JudgeScope::Staged->equals($scope) => 'staged',
            JudgeScope::Branch->equals($scope) => 'branch',
            default => 'full',
        };
    }

    /**
     * The frozen file list for a scope kind: branch/staged via git, full = the scroll.
     * The git-derived sets are RESTRICTED to the scroll's scope (its `path`,
     * `extensions`, `exclude`) — exactly as `judge --git/--staged`
     * ({@see \JesseGall\CodeCommandments\Support\ScrollManager::judgeFiles()}) and
     * the absolve/report resolvers do. Without this the walk would surface findings
     * in files OUTSIDE the scroll (e.g. an excluded `tests/` tree) that the gate and
     * the absolve/report resolvers can't see — wedging the agent on a finding it
     * can neither fix in scope nor absolve.
     *
     * @return list<string>
     */
    private function filesForScopeKind(string $kind): array
    {
        return match ($kind) {
            'staged' => $this->filterToScroll(GitFileDetector::for($this->basePath)->getStagedFiles()),
            'branch' => $this->filterToScroll(GitFileDetector::for($this->basePath)->getBranchFiles()),
            default => $this->scopeFiles(),
        };
    }

    /**
     * Keep only the files that fall within the active scroll's scope — its
     * configured `path`, `extensions`, and `exclude` — via the single
     * {@see ScrollScope} authority that `judge --git/--staged` and the
     * absolve/report resolvers also use. So the walk, the push gate, and
     * absolve/report all judge the SAME file set, and an excluded file can never
     * be surfaced by the walk yet be unresolvable by absolve/report.
     *
     * @param  list<string>  $files
     * @return list<string>
     */
    private function filterToScroll(array $files): array
    {
        $backend = $this->config['scrolls'][$this->scroll] ?? [];

        return ScrollScope::fromConfig($this->basePath, $backend)->filter($files);
    }

    /**
     * Verify the current prophet is resolved, then step forward. Returns null when
     * no walk is in progress.
     *
     * @return array<string, mixed>|null
     */
    public function advance(): ?array
    {
        $state = PilgrimageState::load($this->basePath);

        if ($state === null) {
            return null;
        }

        if ($state->complete) {
            return $this->completeStep();
        }

        // Verify-before-advance: re-scan ONLY the current prophet.
        $current = $this->currentProphet($state);

        if ($current !== null) {
            $locations = $this->scan($current, $state);

            if ($locations !== []) {
                $step = $this->stepFor($state, $current, $locations, resolvedReminder: true);
                $state->save($this->basePath);

                return $step;
            }
        }

        if (! $this->advanceCursor($state)) {
            $state->complete = true;
            $state->save($this->basePath);

            return $this->completeStep();
        }

        $step = $this->walkFrom($state);
        $state->save($this->basePath);

        return $step;
    }

    /**
     * From the cursor, scan each prophet in turn and stop at the first with
     * findings; mark the walk complete when the itinerary is exhausted.
     *
     * @return array<string, mixed>
     */
    private function walkFrom(PilgrimageState $state): array
    {
        while (true) {
            $prophet = $this->currentProphet($state);

            if ($prophet !== null) {
                $locations = $this->scan($prophet, $state);

                if ($locations !== []) {
                    return $this->stepFor($state, $prophet, $locations);
                }
            }

            if (! $this->advanceCursor($state)) {
                $state->complete = true;

                return $this->completeStep();
            }
        }
    }

    private function advanceCursor(PilgrimageState $state): bool
    {
        $itinerary = $this->itinerary();
        $state->prophet++;

        while ($state->doctrine < count($itinerary)) {
            $pillars = $itinerary[$state->doctrine]['pillars'];

            if ($state->pillar < count($pillars)) {
                if ($state->prophet < count($pillars[$state->pillar])) {
                    return true;
                }

                $state->pillar++;
                $state->prophet = 0;

                continue;
            }

            $state->doctrine++;
            $state->pillar = 0;
            $state->prophet = 0;
        }

        return false;
    }

    private function currentProphet(PilgrimageState $state): ?Commandment
    {
        $class = $this->itinerary()[$state->doctrine]['pillars'][$state->pillar][$state->prophet] ?? null;

        return $class === null ? null : ($this->prophetsByClass()[$class] ?? null);
    }

    /**
     * @return list<array{file: string, line: int|null, message: string}>
     */
    private function scan(Commandment $prophet, PilgrimageState $state): array
    {
        $index = $prophet instanceof NeedsCodebaseIndex ? $this->index($state) : new CodebaseIndex();

        return (new Pilgrimage)->scanProphet($prophet, $state->scope, $index, $this->basePath, $this->tracker(), $state->allowWarnings);
    }

    /**
     * The absolution/report ledger, so the walk skips findings the consumer has
     * already absolved or reported (#213). Built from the configured tablet path.
     */
    private function tracker(): ConfessionTracker
    {
        if ($this->tracker !== null) {
            return $this->tracker;
        }

        $tabletPath = $this->config['confession']['tablet_path']
            ?? Environment::basePath('.commandments/confessions.json');

        return $this->tracker = new JsonConfessionTracker((string) $tabletPath, new Filesystem());
    }

    /**
     * @param  list<array{file: string, line: int|null, message: string}>  $locations
     * @return array<string, mixed>
     */
    private function stepFor(PilgrimageState $state, Commandment $prophet, array $locations, bool $resolvedReminder = false): array
    {
        $station = $this->itinerary()[$state->doctrine] ?? ['name' => 'singletons', 'pillars' => []];
        $roster = $this->doctrineRoster($station);
        $current = (new ReflectionClass($prophet))->getShortName();
        [$walked, $total] = $this->overallProgress($state);

        return [
            'complete' => false,
            'prophet' => $current,
            'scripture' => $this->scriptureOf($prophet),
            'doctrine' => $station['name'],
            'doctrineIndex' => $state->doctrine,
            'pillar' => $state->pillar,
            'locations' => $locations,
            'stillUnresolved' => $resolvedReminder,
            // Progress: where we are in the doctrine + across the whole walk.
            'doctrineRoster' => $roster,
            'doctrineProphetPosition' => array_search($current, $roster, true),
            'prophetsWalked' => $walked,
            'prophetsTotal' => $total,
        ];
    }

    /**
     * The short names of every prophet in a doctrine (across its pillars), in walk
     * order — the checklist the agent works through for this doctrine.
     *
     * @param  array{name: string, pillars: list<list<class-string<Commandment>>>}  $station
     * @return list<string>
     */
    private function doctrineRoster(array $station): array
    {
        $names = [];

        foreach ($station['pillars'] ?? [] as $pillar) {
            foreach ($pillar as $class) {
                $names[] = $this->shortName($class);
            }
        }

        return $names;
    }

    /**
     * How many prophets have been WALKED (passed or current) versus the total across
     * the whole itinerary — the "how far am I" figure, useful in penance.
     *
     * @return array{0: int, 1: int}
     */
    private function overallProgress(PilgrimageState $state): array
    {
        $itinerary = $this->itinerary();
        $total = 0;
        $walked = 0;

        foreach ($itinerary as $d => $station) {
            foreach ($station['pillars'] as $p => $pillar) {
                foreach (array_keys($pillar) as $i) {
                    $total++;

                    if ($d < $state->doctrine
                        || ($d === $state->doctrine && $p < $state->pillar)
                        || ($d === $state->doctrine && $p === $state->pillar && $i <= $state->prophet)
                    ) {
                        $walked++;
                    }
                }
            }
        }

        return [$walked, $total];
    }

    private function shortName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function completeStep(): array
    {
        $total = $this->overallProgress(new PilgrimageState(doctrine: PHP_INT_MAX))[1];

        return ['complete' => true, 'locations' => [], 'prophetsTotal' => $total];
    }

    private function scriptureOf(Commandment $prophet): string
    {
        if (method_exists($prophet, 'detailedDescription')) {
            return (string) $prophet->detailedDescription();
        }

        return method_exists($prophet, 'description') ? (string) $prophet->description() : '';
    }

    public function totalDoctrines(): int
    {
        return count($this->itinerary());
    }

    /**
     * A one-line, human-readable description of the active walk's SCOPE — so every
     * pilgrimage/next/todo output can state what it is walking (the scope is otherwise
     * invisible state that changes per profile). Empty when no walk is active.
     */
    public function scopeSummary(): string
    {
        $state = PilgrimageState::load($this->basePath);

        if ($state === null) {
            return '';
        }

        if ($state->onlyProphet !== null) {
            return sprintf('ONE prophet — %s, across %d file(s)', $this->shortName($state->onlyProphet), count($state->scope));
        }

        $where = match ($state->scopeKind) {
            'staged' => 'your STAGED changes',
            'branch' => "the BRANCH's changes",
            default => 'the WHOLE scroll',
        };

        return sprintf('%s (%d file(s)) · %s', $where, count($state->scope), $state->allowWarnings ? 'sins + admonitions' : 'sins only');
    }

    /** The short name of the prophet a single-prophet walk is constrained to, or null. */
    public function singleProphetShort(): ?string
    {
        $only = PilgrimageState::load($this->basePath)?->onlyProphet;

        return $only !== null ? $this->shortName($only) : null;
    }

    /**
     * Whether the walk is GENUINELY complete for the current session — used by the
     * pre-push gate to grant a completed pilgrimage one push past the gate. We do not
     * trust the persisted `complete` flag alone (an agent could hand-write it): the
     * cursor must actually have run past the last doctrine, AND the walk must be owned
     * by the calling session. A forged `complete:true` with the cursor still at the
     * start fails the cursor check and does NOT relax the gate.
     */
    public function isComplete(): bool
    {
        $state = PilgrimageState::load($this->basePath);

        if ($state === null || ! $state->complete) {
            return false;
        }

        if ($state->owner === '' || $state->owner !== PilgrimageState::currentSession()) {
            return false;
        }

        if ($state->doctrine < $this->totalDoctrines()) {
            return false;
        }

        // A single-prophet walk covers ONE rule — it can never relax a gate that
        // judges the whole scope. The gate falls through to its own probe.
        if ($state->onlyProphet !== null) {
            return false;
        }

        // The walk only earns a gate bypass for the scope it ACTUALLY covered. If the
        // active profile's scope/severity changed since `begin()` (the user switched
        // profiles), or files have entered the live scope that the frozen walk never
        // visited, the completion is stale — do NOT relax; the gate re-probes.
        $opts = ProfileService::resolve($this->basePath)->options();

        if ($this->scopeKindFor($opts->scope) !== $state->scopeKind || $opts->allowWarnings !== $state->allowWarnings) {
            return false;
        }

        $live = $this->normalizePaths($this->filesForScopeKind($state->scopeKind));
        $frozen = $this->normalizePaths($state->scope);

        return array_diff($live, $frozen) === [];
    }

    /**
     * Canonicalize a file list (realpath, drop unresolved) so a branch/staged file
     * list produced now is comparable to the one frozen at `begin()` — guards the
     * relative-vs-absolute / symlink-prefix mismatch the gate guard would otherwise
     * trip on.
     *
     * @param  list<string>  $files
     * @return list<string>
     */
    private function normalizePaths(array $files): array
    {
        $out = [];

        foreach ($files as $file) {
            $real = realpath($file);

            if ($real !== false) {
                $out[$real] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * @return list<array{name: string, pillars: list<list<class-string<Commandment>>>}>
     */
    private function itinerary(): array
    {
        return $this->itinerary ??= Pilgrimage::itinerary(
            array_keys($this->prophetsByClass()),
            $this->onlyProphet ?? PilgrimageState::load($this->basePath)?->onlyProphet,
        );
    }

    /**
     * @return array<class-string<Commandment>, Commandment>
     */
    private function prophetsByClass(): array
    {
        if ($this->prophetsByClass !== null) {
            return $this->prophetsByClass;
        }

        $backend = $this->config['scrolls'][$this->scroll] ?? [];
        $registry = new ProphetRegistry();
        $registry->registerMany($this->scroll, $backend['prophets'] ?? []);
        $registry->setScrollConfig($this->scroll, $backend);

        $byClass = [];

        foreach ($registry->getProphets($this->scroll) as $prophet) {
            $byClass[$prophet::class] = $prophet;
        }

        return $this->prophetsByClass = $byClass;
    }

    /**
     * @return list<string>
     */
    private function scopeFiles(): array
    {
        $backend = $this->config['scrolls'][$this->scroll] ?? [];
        $path = $backend['path'] ?? $this->basePath;
        $extensions = $backend['extensions'] ?? ['php'];
        $exclude = $backend['exclude'] ?? [];

        $files = [];

        foreach ((new GenericFileScanner())->scan($path, $extensions, $exclude) as $file) {
            $files[] = $file instanceof \SplFileInfo ? $file->getPathname() : (string) $file;
        }

        return $files;
    }

    private function index(PilgrimageState $state): CodebaseIndex
    {
        // The cross-file index is ALWAYS built from the full scroll, even when the
        // walk's reported scope is narrowed (branch/staged) — so origin traces and
        // NeedsCodebaseIndex prophets resolve callers outside the scope, exactly as
        // `judge --branch/--staged` does.
        return $this->index ??= (new PilgrimageIndexCache())->get($this->basePath, $this->scopeFiles());
    }

    /**
     * Resolve a partial prophet name (like `judge --prophet`, but STRICTER) to a
     * single registered prophet class. Exactly one match → its FQCN; zero or many →
     * null + the candidate short names, so the caller can refuse and list them.
     *
     * @return array{class: class-string<Commandment>|null, candidates: list<string>}
     */
    public function resolveProphet(string $needle): array
    {
        $needle = strtolower(trim($needle));
        $registered = array_keys($this->prophetsByClass());

        if ($needle === '') {
            return ['class' => null, 'candidates' => array_map($this->shortName(...), $registered)];
        }

        $matches = array_values(array_filter(
            $registered,
            fn (string $class): bool => str_contains(strtolower($this->shortName($class)), $needle),
        ));

        if (count($matches) === 1) {
            return ['class' => $matches[0], 'candidates' => []];
        }

        return ['class' => null, 'candidates' => array_map($this->shortName(...), $matches !== [] ? $matches : $registered)];
    }

    /**
     * Short name => finding count for every prophet that currently fires over the
     * full scroll, most first — the ranked menu shown when a single-prophet walk is
     * started without naming a prophet. Scans every prophet, so it is for the
     * refusal/menu path only, not the hot loop.
     *
     * @return array<string, int>
     */
    public function prophetFindingCounts(): array
    {
        $files = $this->scopeFiles();
        $index = (new PilgrimageIndexCache())->get($this->basePath, $files);
        $pilgrimage = new Pilgrimage;
        $counts = [];

        foreach ($this->prophetsByClass() as $class => $prophet) {
            $idx = $prophet instanceof NeedsCodebaseIndex ? $index : new CodebaseIndex();
            $count = count($pilgrimage->scanProphet($prophet, $files, $idx, $this->basePath, $this->tracker()));

            if ($count > 0) {
                $counts[$this->shortName($class)] = $count;
            }
        }

        arsort($counts);

        return $counts;
    }

    /**
     * If $targetFqcn is a RootCauseMap symptom whose cause still fires in scope, the
     * cause's short name (so a single-prophet walk can refuse to repent the symptom
     * in isolation and point at the cause) — else null.
     *
     * @param  class-string<Commandment>  $targetFqcn
     */
    public function unresolvedCauseFor(string $targetFqcn): ?string
    {
        $files = $this->scopeFiles();
        $pilgrimage = new Pilgrimage;

        foreach (RootCauseMap::causesOf($targetFqcn) as $causeClass) {
            $prophet = $this->prophetsByClass()[$causeClass] ?? null;

            if ($prophet === null) {
                continue;
            }

            $index = $prophet instanceof NeedsCodebaseIndex ? (new PilgrimageIndexCache())->get($this->basePath, $files) : new CodebaseIndex();

            if ($pilgrimage->scanProphet($prophet, $files, $index, $this->basePath, $this->tracker()) !== []) {
                return $this->shortName($causeClass);
            }
        }

        return null;
    }
}
