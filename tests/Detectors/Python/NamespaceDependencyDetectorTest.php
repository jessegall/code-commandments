<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\NamespaceDependencyDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

final class NamespaceDependencyDetectorTest extends TestCase
{
    private const array SHOP = [
        '/p/shop/__init__.py' => '',
        '/p/shop/ui/__init__.py' => '',
        '/p/shop/ui/elements/__init__.py' => '',
        '/p/shop/ui/elements/button.py' => "from shop.ui.shared.panel import Panel\n\n\ndef button():\n    return Panel()\n",
        '/p/shop/ui/shared/__init__.py' => '',
        '/p/shop/ui/shared/panel.py' => "from ..elements import button\n\n\nclass Panel:\n    pass\n",
        '/p/shop/domain/__init__.py' => '',
        '/p/shop/domain/order.py' => "import json\n\n\ndef total():\n    from shop.ui.shared import panel\n    return panel\n",
        '/p/shop/reports.py' => "from shop.ui.elements.button import button\n",
    ];

    public function test_flags_an_import_its_layer_did_not_declare(): void
    {
        $detector = new NamespaceDependencyDetector()
            ->layer('shop.ui.elements')
            ->layer('shop.ui.shared', mayUse: ['shop.ui.elements'])
            ->layer('shop.domain');

        $this->assertSame(['domain/order.py:5', 'ui/elements/button.py:1'], $this->locations($detector));
    }

    public function test_nothing_is_judged_without_a_declaration(): void
    {
        $this->assertSame([], $this->locations(new NamespaceDependencyDetector()));
    }

    public function test_an_undeclared_target_or_referrer_is_free(): void
    {
        $this->assertSame([], $this->locations(new NamespaceDependencyDetector()->layer('shop.ui.shared')));
    }

    /**
     * @return list<string>
     */
    private function locations(NamespaceDependencyDetector $detector): array
    {
        $found = array_map(static fn (NodeMatch $match): string => str_replace('/p/shop/', '', $match->location()), $detector->find(new Codebase(self::SHOP)));
        sort($found);

        return $found;
    }
}
