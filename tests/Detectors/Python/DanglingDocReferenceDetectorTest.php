<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\DanglingDocReferenceDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

final class DanglingDocReferenceDetectorTest extends TestCase
{
    private const array SHOP = [
        '/p/shop/__init__.py' => "from shop.cart import Cart\n",
        '/p/shop/cart.py' => "RATE = 3\n\n\nclass Cart:\n    pass\n\n\ndef total(cart):\n    return 0\n",
    ];

    public function test_flags_a_first_party_reference_nothing_declares(): void
    {
        $this->assertSame(['FunctionDef ship'], $this->scopesIn("def ship(cart):\n    \"\"\"Ship a :class:`shop.cart.Basket`.\"\"\"\n"));
        $this->assertSame(['ClassDef Label'], $this->scopesIn("class Label:\n    \"\"\"Printed by :func:`~shop.printing.label`.\"\"\"\n"));
    }

    public function test_leaves_references_that_resolve(): void
    {
        $this->assertSame([], $this->scopesIn("def ship(cart):\n    \"\"\"Ship a :class:`shop.cart.Cart` at :data:`shop.cart.RATE`, see :func:`total <shop.cart.total>`.\"\"\"\n"));
        $this->assertSame([], $this->scopesIn("def ship(cart):\n    \"\"\"Ship a :class:`shop.Cart`, re-exported by the package.\"\"\"\n"));
        $this->assertSame([], $this->scopesIn("def ship(cart):\n    \"\"\"See :mod:`shop.cart` and :meth:`shop.cart.Cart.add`.\"\"\"\n"));
    }

    public function test_leaves_what_it_cannot_verify(): void
    {
        $this->assertSame([], $this->scopesIn("def ship(cart):\n    \"\"\"Returns a :class:`flask.Response`, like :meth:`dumps` does.\"\"\"\n"));
        $this->assertSame([], $this->scopesIn("def log(cart):\n    \"\"\"Writes through a :class:`logging.StreamHandler`.\"\"\"\n", ['/p/shop/logging.py' => "def handler():\n    pass\n"]));
    }

    /**
     * @param  array<string, string>  $more
     * @return list<string>
     */
    private function scopesIn(string $source, array $more = []): array
    {
        $codebase = new Codebase([...self::SHOP, ...$more, '/p/shop/shipping.py' => $source]);

        return array_map(static fn (NodeMatch $match): string => $match->scope(), new DanglingDocReferenceDetector()->find($codebase));
    }
}
