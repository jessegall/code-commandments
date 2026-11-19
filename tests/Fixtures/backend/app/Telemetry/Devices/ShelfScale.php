<?php

namespace Shop\Telemetry\Devices;

use JesseGall\CodeCommandments\Sins\Backend\RefusedBequest;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A device on the shop floor that reports a reading and can be zeroed by staff.
 */
interface FloorDevice
{
    public function reading(): float;

    public function zero(): void;
}

/**
 * A shelf scale with a fixed factory tare: it averages its last samples, and refuses the zeroing its contract asks.
 */
final class ShelfScale implements FloorDevice
{
    /**
     * @var list<float>
     */
    private array $samples = [];

    public function sample(float $grams): void
    {
        $this->samples = array_slice([...$this->samples, $grams], -5);
    }

    public function reading(): float
    {
        return $this->samples === [] ? 0.0 : round(array_sum($this->samples) / count($this->samples), 1);
    }

    #[Sinful(RefusedBequest::class)]
    public function zero(): void
    {
        throw new ScaleTareIsFixed();
    }
}

/**
 * Zeroing a scale whose tare is set at the factory.
 */
final class ScaleTareIsFixed extends \LogicException
{
}
