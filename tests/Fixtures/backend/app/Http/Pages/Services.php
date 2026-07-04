<?php

namespace Shop\Http\Pages;

/**
 * The container-resolved collaborators a page object injects to project its slots — services, not
 * page data. A page object pulls these in via `#[FromContainer]` and MUST `#[Hidden]` them.
 */
final class SalesReporter {}

final class CartReader {}

final class CatalogReader {}

final class FacetBuilder {}
