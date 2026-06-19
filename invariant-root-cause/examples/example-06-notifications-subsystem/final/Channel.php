<?php

declare(strict_types=1);

namespace Notifications;

interface Channel
{
    public function send(string $to, string $body): void;
}
