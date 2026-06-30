<?php

namespace Shop\Orders;

final class RoutingContext
{
    public function __construct(public readonly NodeDescriptor $descriptor) {}
}
