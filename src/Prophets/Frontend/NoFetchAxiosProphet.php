<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Use Inertia requests instead of fetch/axios.
 *
 * Never use fetch() or axios for API calls in Vue components.
 * Use Inertia's router for all server communication.
 */
class NoFetchAxiosProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Use Inertia requests instead of fetch/axios';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Never use fetch() or axios for API calls in Vue components.

Use Inertia's router for all server communication. This maintains the
single-page app experience and provides proper TypeScript integration.

Bad:
    const data = await fetch('/api/products').then(r => r.json());
    const response = await axios.get('/products');

Good:
    router.get(products.index.url());
    router.post(products.store.url(), form);
    form.post(products.store.url());
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        $script = $this->extractScript($content);

        if ($script === null) {
            return $this->skip('No script section found');
        }

        $scriptContent = $script['content'];
        $scriptStart = $script['start'];

        // Look for fetch() or axios usage
        $pattern = '/(fetch\(|axios\.|window\.fetch)/';

        if (preg_match($pattern, $scriptContent, $match, PREG_OFFSET_CAPTURE)) {
            // Allow fetch if there's a comment indicating it's calling an API controller
            if (preg_match('/\/\/.*(?:API endpoint|Api\\\\|returns JSON)/i', $content)) {
                return $this->righteous();
            }

            $offset = $match[0][1];
            $line = $this->getLineFromOffset($content, $scriptStart + $offset);

            return $this->fallen([
                $this->sinAt(
                    $line,
                    'fetch() or axios usage detected',
                    $this->getSnippet($scriptContent, $offset, 50),
                    "Use Inertia's router for server communication"
                ),
            ]);
        }

        return $this->righteous();
    }
}
