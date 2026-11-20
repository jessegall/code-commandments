<?php

namespace Shop\Http\Pages;

/**
 * The container-resolved collaborators a page object injects to project its slots — services, not
 * page data. A page object pulls these in via `#[FromContainer]` and MUST `#[Hidden]` them.
 */
interface SalesReporter
{
    public function primaryLink(): MenuLink;

    public function secondaryLink(): MenuLink;

    public function featuredLine(): CartLine;

    public function totals(): WarehouseTotals;

    public function movers(): array;
}

interface WarehouseTotals
{
    public function summary(): StatCard;

    public function homeLink(): MenuLink;
}

interface CartReader
{
    public function firstLine(): CartLine;

    public function backLink(): MenuLink;
}

final class CatalogReader {}

interface FacetBuilder
{
    public function headline(): StatCard;

    public function cards(): array;
}

final class AiService
{
    public function isEnabled(): bool
    {
        return true;
    }
}

final class ContainersService
{
    public function healthy(): bool
    {
        return true;
    }
}

final class LogBuffer
{
    public function size(): int
    {
        return 0;
    }
}
