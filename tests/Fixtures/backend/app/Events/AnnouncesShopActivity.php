<?php

namespace Shop\Events;

/**
 * The contract a host application stamps on its OWN events to route them into the shop's activity feed.
 * Nothing here implements it — by design: the implementors are the consumer's, outside this tree.
 */
interface AnnouncesShopActivity
{
    public function activityKey(): string;
}
