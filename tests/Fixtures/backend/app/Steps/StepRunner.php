<?php

namespace Shop\Steps;

/**
 * Runs the steps of a checkout in order and collects their lines, until one of them stops the run.
 */
final class StepRunner
{
    /**
     * @param  list<RunStep>  $steps
     * @return list<string>
     */
    public function run(array $steps): array
    {
        $lines = [];
        try {
            foreach ($steps as $step) {
                $lines[] = $step->handle();
            }
        } catch (StopRun) {
            return $lines;
        }

        return $lines;
    }
}
