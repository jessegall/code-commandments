<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Page components should use PageData indexed access for prop types.
 *
 * In page components, prop types should reference the PageData type using
 * indexed access instead of direct Data types.
 */
class PageDataAccessProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Page components should use PageData indexed access for prop types';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
In page components, prop types should reference the PageData type using
indexed access instead of direct Data types.

Bad:
    interface Props {
        products: ProductData[];
        user: UserData;
    }

Good:
    type ProductsIndexPage = App.Http.View.Products.ProductsIndexPage;
    interface Props {
        products: ProductsIndexPage['products'];
        user: ProductsIndexPage['user'];
    }

This ensures type consistency with the backend Page Data class.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        return VuePipeline::make($filePath, $content)
            ->onlyPageFiles()
            ->excludePartialFiles()
            ->inScript()
            ->returnRighteousIfSectionMatches('/Page\[/i')
            ->matchAll('/^\s+\w+:\s+(?:App\.Data\.|[A-Z][a-zA-Z]+Data)(?:\[\])?;/m')
            ->sinsFromMatches(
                "Using direct Data types - should use PageData['prop'] indexed access",
                "Use type YourPage = App.Http.View.Path.YourPage; then YourPage['propName']"
            )
            ->judge();
    }
}
