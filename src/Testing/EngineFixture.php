<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Support\ClassName;

/**
 * What a fixture is before either engine parses a byte of it: a DIRECTORY, the detectors to prove
 * against it, and the directory's OWN `.commandments/config.php` — because a fixture directory is a
 * project. A rule inert until the project declares something (a layer map, a threshold) fires only
 * once declared, so the fixture declares it exactly as a consumer would and tunes the detectors
 * here, before anything is verified. The file is never scanned as fixture SOURCE: every engine
 * prunes `.`-prefixed directories. A declaration aimed at a detector this fixture omits is ignored,
 * since a fixture legitimately holds a subset of the catalog.
 *
 * @see BackendFixture the PHP engine's fixture
 * @see FrontendFixture the Vue engine's — same mechanism
 * @see ModuleFixture the Python and C# engines' — same mechanism
 */
abstract class EngineFixture implements Fixture
{
    /**
     * @var list<Detector>
     */
    protected readonly array $detectors;

    /**
     * @param  list<Detector>  $detectors
     */
    public function __construct(
        protected readonly string $path,
        array $detectors,
    ) {
        Config::load($path)->tune($detectors);

        $this->detectors = $detectors;
    }

    /**
     * The detectors this fixture holds no `$tag` marker for — `fixed` (the resolution a skill publishes
     * as Good) or `righteous` (a look-alike the rule must leave alone) — by short name. A marker names
     * a detector by its class, its sin's class, or its sin's id, short or whole.
     *
     * @return list<string>
     */
    public function withoutMarker(string $tag): array
    {
        $marked = $this->markedNames($tag);
        $missing = [];

        foreach ($this->detectors as $detector) {
            $names = [$detector::class, ClassName::short($detector::class), $detector->sin()::class, ClassName::short($detector->sin()::class), $detector->sin()->name()];

            if (! array_any($names, static fn (string $name): bool => isset($marked[$name]))) {
                $missing[] = ClassName::short($detector::class);
            }
        }

        return $missing;
    }

    /**
     * Every name a `$tag` marker in this fixture carries.
     *
     * @return array<string, true>
     */
    abstract protected function markedNames(string $tag): array;

    /**
     * The worked examples its markers carve out of this fixture, per detector — what a skill publishes
     * as each rule's Bad and Good.
     *
     * @return array<class-string<Detector>, list<Example>>
     */
    abstract public function examples(): array;
}
