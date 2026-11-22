<?php

namespace Illuminate\Bus;

interface BatchRepository
{
    /** @return \Illuminate\Bus\Batch[] */
    public function get($limit, $before);
    /** @return \Illuminate\Bus\Batch|null */
    public function find(string $batchId);
    /** @return \Illuminate\Bus\Batch */
    public function store(\Illuminate\Bus\PendingBatch $batch);
    /** @return void */
    public function incrementTotalJobs(string $batchId, int $amount);
    /** @return \Illuminate\Bus\UpdatedBatchJobCounts */
    public function decrementPendingJobs(string $batchId, string $jobId);
    /** @return \Illuminate\Bus\UpdatedBatchJobCounts */
    public function incrementFailedJobs(string $batchId, string $jobId);
    /** @return void */
    public function markAsFinished(string $batchId);
    /** @return void */
    public function cancel(string $batchId);
    /** @return void */
    public function delete(string $batchId);
    /** @return mixed */
    public function transaction(\Closure $callback);
    /** @return void */
    public function rollBack();
}
