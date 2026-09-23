<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\NamespaceCycleDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

final class NamespaceCycleDetectorTest extends TestCase
{
    public function test_flags_the_imports_of_one_side_of_a_mutual_pair_the_name_breaking_a_tie(): void
    {
        $this->assertSame(['orders/ship.py:1'], $this->locations([
            '/p/shop/__init__.py' => '',
            '/p/shop/orders/__init__.py' => '',
            '/p/shop/orders/ship.py' => "from shop.ui.panel import Panel\n\n\ndef ship():\n    return Panel()\n",
            '/p/shop/ui/__init__.py' => '',
            '/p/shop/ui/panel.py' => "from ..orders import ship\n\n\nclass Panel:\n    pass\n",
        ]));
    }

    public function test_an_import_hidden_in_a_function_is_still_an_arrow(): void
    {
        $this->assertSame(['orders/ship.py:6'], $this->locations([
            '/p/shop/__init__.py' => '',
            '/p/shop/orders/__init__.py' => '',
            '/p/shop/orders/ship.py' => "class Order:\n    pass\n\n\ndef ship():\n    from shop.ui import panel\n    return panel\n",
            '/p/shop/ui/__init__.py' => '',
            '/p/shop/ui/panel.py' => "import shop.orders.ship\n",
        ]));
    }

    public function test_the_thinner_side_is_the_one_reported(): void
    {
        $this->assertSame(['ui/panel.py:1'], $this->locations([
            '/p/shop/__init__.py' => '',
            '/p/shop/orders/__init__.py' => '',
            '/p/shop/orders/ship.py' => "from shop.ui.panel import Panel\n",
            '/p/shop/orders/lines.py' => "from shop.ui.panel import Panel\n",
            '/p/shop/ui/__init__.py' => '',
            '/p/shop/ui/panel.py' => "from shop.orders.ship import ship\n\n\nclass Panel:\n    pass\n",
        ]));
    }

    public function test_leaves_one_way_imports_and_imports_within_a_package(): void
    {
        $this->assertSame([], $this->locations([
            '/p/shop/__init__.py' => '',
            '/p/shop/orders/__init__.py' => '',
            '/p/shop/orders/ship.py' => "from shop.ui.panel import Panel\nfrom shop.orders.lines import Line\n",
            '/p/shop/orders/lines.py' => "from shop.orders.ship import ship\n",
            '/p/shop/ui/__init__.py' => '',
            '/p/shop/ui/panel.py' => "import json\n",
        ]));
    }

    /**
     * @param  array<string, string>  $sources
     * @return list<string>
     */
    private function locations(array $sources): array
    {
        $found = array_map(static fn (NodeMatch $match): string => str_replace('/p/shop/', '', $match->location()), new NamespaceCycleDetector()->find(new Codebase($sources)));
        sort($found);

        return $found;
    }
}
