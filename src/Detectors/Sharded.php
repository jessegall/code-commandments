<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

use Closure;
use JesseGall\CodeCommandments\Ast\Codebase;

/**
 * A {@see Detector} whose `find()` is dominated by an independent per-candidate
 * loop — the same expensive check run over each of N candidates, additive into one
 * result list. Such a detector implements `shards()` to hand that loop to the
 * runner as N separate tasks, so the work spreads across the whole worker pool
 * instead of pinning one core (see {@see \JesseGall\CodeCommandments\Concurrency\Fork}).
 *
 * `find()` must still return the WHOLE result (the un-sharded path: a single
 * `--detector=X` run, or a build without forking). The contract is that
 * `array_merge(...map(fn ($s) => $s(), shards($cb)))` equals `find($cb)` — sharding
 * only changes WHERE the work runs, never WHAT it finds.
 */
interface Sharded extends Detector
{
    /**
     * The detector's work split into independent units. Each closure runs one
     * shard and returns its matches; the union over all shards equals `find()`.
     *
     * @return list<Closure(): list<\JesseGall\CodeCommandments\Ast\NodeMatch>>
     */
    public function shards(Codebase $codebase): array;
}
