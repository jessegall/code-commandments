<?php

namespace Illuminate\Contracts\Queue;

interface QueueableCollection
{
    /** @return string|null */
    public function getQueueableClass();
    /** @return array<int, */
    public function getQueueableIds();
    /** @return array<int, */
    public function getQueueableRelations();
    /** @return string|null */
    public function getQueueableConnection();
}
