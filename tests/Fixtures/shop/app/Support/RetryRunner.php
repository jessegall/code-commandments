<?php

namespace Shop\Support;

use Closure;
use JesseGall\CodeCommandments\Detectors\Backend\NullableCallbackDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

final class RetryRunner
{
    private int $attempts = 0;

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @param  Closure(int): void|null  $onRetry
     *
     * @return T
     */
    #[Sinful(NullableCallbackDetector::class)]
    public function run(Closure $work, Closure | null $onRetry = null): mixed
    {
        while (true) {
            $this->attempts++;

            try {
                return $work();
            } catch (\Throwable $e) {
                if ($onRetry) {
                    $onRetry($this->attempts);
                }

                if ($this->attempts >= 3) {
                    throw $e;
                }
            }
        }
    }
}
