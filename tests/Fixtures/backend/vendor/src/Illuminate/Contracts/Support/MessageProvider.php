<?php

namespace Illuminate\Contracts\Support;

interface MessageProvider
{
    /** @return \Illuminate\Contracts\Support\MessageBag */
    public function getMessageBag();
}
