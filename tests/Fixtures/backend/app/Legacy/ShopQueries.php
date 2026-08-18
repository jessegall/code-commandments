<?php

namespace Shop\Legacy;

use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * A shelf of named GraphQL documents — the righteous twin of a const class. Its consts are CONTENT,
 * not a closed set of values: nothing compares them, dispatches on them, or hangs behaviour off
 * them, and no enum could carry a twenty-line query as a case. A rule about closed sets must leave
 * it alone (#505, #508).
 */
#[Righteous]
final class ShopQueries
{
    /**
     * The shop's own profile.
     */
    public const string SHOP = <<<'GQL'
        query {
          shop {
            name
            email
          }
        }
        GQL;

    /**
     * The shop's primary domain, for building absolute storefront links.
     */
    public const string PRIMARY_DOMAIN = <<<'GQL'
        query {
          shop {
            primaryDomain {
              url
              host
            }
          }
        }
        GQL;
}
