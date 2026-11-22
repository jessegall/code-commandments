<?php

namespace Illuminate\Contracts\Queue;

interface QueueableEntity
{
    /** @return mixed */
    public function getQueueableId();
    /** @return array */
    public function getQueueableRelations();
    /** @return string|null */
    public function getQueueableConnection();
}
