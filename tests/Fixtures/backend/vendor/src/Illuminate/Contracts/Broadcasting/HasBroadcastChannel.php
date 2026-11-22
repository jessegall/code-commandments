<?php

namespace Illuminate\Contracts\Broadcasting;

interface HasBroadcastChannel
{
    /** @return string */
    public function broadcastChannelRoute();
    /** @return string */
    public function broadcastChannel();
}
