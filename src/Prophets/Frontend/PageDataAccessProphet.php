<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

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

        // Only check files in Pages directory
        if (!str_contains($filePath, '/pages/') && !str_contains($filePath, '/Pages/')) {
            return $this->righteous();
        }

        // Skip partial components - they receive props from parent pages, not from the backend
        if (str_contains($filePath, '/Partials/') || str_contains($filePath, '/partials/')) {
            return $this->righteous();
        }

        $script = $this->extractScript($content);

        if ($script === null) {
            return $this->skip('No script section found');
        }

        $scriptContent = $script['content'];
        $scriptStart = $script['start'];

        // Look for direct Data types in props (not using PageData['prop'] access)
        // Pattern: property: SomeData or property: SomeData[]
        $directDataPattern = '/^\s+\w+:\s+(?:App\.Data\.|[A-Z][a-zA-Z]+Data)(?:\[\])?;/m';

        if (preg_match($directDataPattern, $scriptContent, $match, PREG_OFFSET_CAPTURE)) {
            // Check if file already uses PageData indexed access
            if (preg_match('/Page\[/i', $scriptContent)) {
                return $this->righteous();
            }

            $offset = $match[0][1];
            $line = $this->getLineFromOffset($content, $scriptStart + $offset);

            return $this->fallen([
                $this->sinAt(
                    $line,
                    "Using direct Data types - should use PageData['prop'] indexed access",
                    $this->getSnippet($scriptContent, $offset, 60),
                    "Use type YourPage = App.Http.View.Path.YourPage; then YourPage['propName']"
                ),
            ]);
        }

        return $this->righteous();
    }
}
