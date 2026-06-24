<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Routes a message to the channel its type designates and delivers it.
 */
final class NotificationDispatcher
{
    public function __construct(
        private readonly ChannelRegistry $channels,
    ) {}

    public function dispatch(Message $message): void
    {
        $channel = $this->channels->get($message->type->channelKey());

        $channel->send($message);
    }
}
