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
            'doctrine' => $this->itinerary()[$state->doctrine]['name'] ?? 'singletons',
            'locations' => $this->scan($prophet, $state),
        ];
    }

    /**
     * Reset the walk and stop at the first prophet that has findings.
     *
     * @return array<string, mixed>
     */
    public function begin(): array
    {
        PilgrimageState::clear($this->basePath);
        PilgrimageIndexCache::clear($this->basePath);

        // A fresh reckoning: clear ordinary finding-absolutions so everything
        // re-surfaces (reported + until-push absolutions are deliberately kept —
        // a reported finding stays quiet until its issue is answered).
        $this->tracker()->clearFindingAbsolutions();

        $state = new PilgrimageState(scope: $this->scopeFiles(), scroll: $this->scroll, owner: PilgrimageState::currentSession());
        $step = $this->walkFrom($state);
        $state->save($this->basePath);

        return $step;
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

        return (new Pilgrimage)->scanProphet($prophet, $state->scope, $index, $this->basePath, $this->tracker());
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
     * @return list<array{name: string, pillars: list<list<class-string<Commandment>>>}>
     */
    private function itinerary(): array
    {
        return $this->itinerary ??= Pilgrimage::itinerary(array_keys($this->prophetsByClass()));
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
        return $this->index ??= (new PilgrimageIndexCache())->get(
            $this->basePath,
            $state->scope !== [] ? $state->scope : $this->scopeFiles(),
        );
    }
}
