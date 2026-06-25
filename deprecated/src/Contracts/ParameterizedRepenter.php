<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Contracts;

use JesseGall\CodeCommandments\Results\RepentInput;

/**
 * A repenter whose fix needs input the tool cannot infer — supplied on the CLI
 * as `--input <name>=<value>`. The repent command reads {@see repentInputs()},
 * validates that every required input is present (printing what is missing
 * otherwise), and hands the collected values to {@see setRepentInput()} before
 * calling {@see SinRepenter::repent()}.
 */
interface ParameterizedRepenter extends SinRepenter
{
    /**
     * The inputs this repenter accepts.
     *
     * @return list<RepentInput>
     */
    public function repentInputs(): array;

    /**
     * Receive the values collected from the CLI, keyed by input name.
     *
     * @param  array<string, string>  $values
     */
    public function setRepentInput(array $values): void;
}
