<?php

namespace Shop\Support;

use JesseGall\CodeCommandments\Sins\Backend\NullableCallback;

use Closure;
use JesseGall\CodeCommandments\Testing\Righteous;
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
    #[Sinful(NullableCallback::class)]
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

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @param  Closure(int): void  $onRetry  a no-op closure stands in when the caller doesn't care
     *
     * @return T
     */
    #[Righteous(NullableCallback::class)]
    public function runWith(Closure $work, Closure $onRetry): mixed
    {
        while (true) {
            $this->attempts++;

            try {
                return $work();
            } catch (\Throwable $e) {
                $onRetry($this->attempts);

                if ($this->attempts >= 3) {
                    throw $e;
                }
            }
        }
    }
}
