<?php

namespace Shop\Imports;

use JesseGall\CodeCommandments\Sins\Backend\ListMirrorsFolder;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Imports\Csv\CommaSeparated;
use Shop\Imports\Csv\SemicolonSeparated;
use Shop\Imports\Csv\TabSeparated;
use Shop\Imports\Xml\GoogleFeed;
use Shop\Imports\Xml\IcecatFeed;
use Shop\Imports\Xml\PlainCatalog;

/**
 * Builds the product import's format book from every CSV and XML layout the shop reads.
 */
final class ImportSetup
{
    #[Sinful(ListMirrorsFolder::class)]
    public static function book(): ImportFormatBook
    {
        return ImportFormatBook::of([CommaSeparated::class, SemicolonSeparated::class, TabSeparated::class, GoogleFeed::class, IcecatFeed::class, PlainCatalog::class]);
    }
}
