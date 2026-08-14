<?php

namespace Shop\Shipping;

/**
 * The seam a fluent query is opened on — the collaborator every chain in
 * {@see ConsignmentLedger} starts from.
 */
final class QueryConnection
{
    public function table(string $name): QueryBuilder
    {
        return new QueryBuilder();
    }
}
