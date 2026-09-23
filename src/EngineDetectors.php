<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The detectors a project runs, told apart by the engine that reads them — what {@see Config::apply}
 * answers. A caller asks for one engine's or for all of them, so adding an engine never leaves a list
 * of two behind.
 */
final readonly class EngineDetectors
{
    /**
     * @param  list<Detector>  $detectors
     */
    public function __construct(private array $detectors) {}

    /**
     * @return list<Detector>
     */
    public function for(Engine $engine): array
    {
        return array_values(array_filter($this->detectors, static fn (Detector $detector): bool => Engine::of($detector) === $engine));
    }

    /**
     * Every engine's detectors, in the order they were registered.
     *
     * @return list<Detector>
     */
    public function all(): array
    {
        return $this->detectors;
    }
}
