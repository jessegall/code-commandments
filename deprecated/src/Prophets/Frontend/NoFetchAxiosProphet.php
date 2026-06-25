<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

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

        return VuePipeline::make($filePath, $content)
            ->returnRighteousIfContentMatches('/\/\/.*(?:API endpoint|Api\\\\|returns JSON)/i')
            ->inScript()
            ->matchAll('/(fetch\(|axios\.|window\.fetch)/')
            ->sinsFromMatches(
                'fetch() or axios usage detected',
                "Use Inertia's router for server communication"
            )
            ->judge();
    }
}
