<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\FilledParameter;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Contracts\HttpClient;

/**
 * Loads the state of the last export run. The loader hands a fresh state to two `void` methods
 * that fill it in and then returns the thing it handed over, so the two signatures say the
 * state goes in and say nothing at all about it coming back changed.
 */
final class ExportStateLoader
{
    public function __construct(private readonly HttpClient $http) {}

    public function load(string $run): ExportState
    {
        $state = new ExportState();
        $this->fillCounts($state, $run);

        return $state;
    }

    #[Sinful(FilledParameter::class)]
    private function fillCounts(ExportState $state, string $run): void
    {
        $state->rows = strlen($this->http->get("https://reports.test/runs/{$run}"));
    }

    #[Fixed(FilledParameter::class)]
    public function loadCounted(string $run): ExportState
    {
        return new ExportState($this->countedRows($run));
    }

    #[Fixed(FilledParameter::class)]
    private function countedRows(string $run): int
    {
        return strlen($this->http->get("https://reports.test/runs/{$run}"));
    }
}
