<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\DuplicateFunctionDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

final class DuplicateFunctionDetectorTest extends TestCase
{
    private const string LOAD = <<<'PY'
            response = http.get(f"/orders?page={page}")
            if response.status != 200:
                raise LoadFailed(response.reason)
            return [item.id for item in response.json()["items"]]
        PY;

    public function test_flags_a_copy_renamed_under_another_function(): void
    {
        $found = $this->findIn(Codebase::fromString("def load_orders(page):\n" . self::LOAD . "\n\ndef fetch_order_ids(page):\n" . self::LOAD . "\n"));

        $this->assertSame(['FunctionDef load_orders', 'FunctionDef fetch_order_ids'], $found);
    }

    public function test_flags_a_method_copying_a_module_function_across_files(): void
    {
        $root = sys_get_temp_dir() . '/dup-py-' . uniqid();
        mkdir($root);
        file_put_contents("{$root}/orders.py", "def load_orders(page):\n" . self::LOAD . "\n");
        file_put_contents("{$root}/client.py", "class Client:\n    def load(self, page):\n" . preg_replace('/^/m', '    ', self::LOAD) . "\n");

        $found = $this->findIn(Codebase::scan($root));

        array_map('unlink', glob("{$root}/*") ?: []);
        rmdir($root);

        $this->assertCount(2, $found);
    }

    public function test_formatting_comments_and_a_docstring_do_not_hide_a_copy(): void
    {
        $reformatted = <<<'PY'
                """Load one page of order ids."""
                response = http.get(f"/orders?page={page}")  # one page
                if response.status != 200: raise LoadFailed(response.reason)
                return [item.id
                        for item in response.json()["items"]]
            PY;

        $found = $this->findIn(Codebase::fromString("def load_orders(page):\n" . self::LOAD . "\n\ndef load(page):\n" . $reformatted . "\n"));

        $this->assertCount(2, $found);
    }

    public function test_ignores_bodies_below_the_floor_and_a_single_function(): void
    {
        $this->assertSame([], $this->findIn(Codebase::fromString("def a(x):\n    return x.total\n\ndef b(x):\n    return x.total\n")));
        $this->assertSame([], $this->findIn(Codebase::fromString("def load_orders(page):\n" . self::LOAD . "\n")));
    }

    public function test_ignores_two_classes_setting_their_own_state_alike(): void
    {
        $init = "    def __init__(self, stream, response, start):\n        self._stream = stream\n        self._response = response\n        self._start = start\n        self._closed = False\n";

        $this->assertSame([], $this->findIn(Codebase::fromString("class A:\n{$init}\n\nclass B:\n{$init}")));
    }

    public function test_a_sync_body_and_its_async_twin_are_not_copies(): void
    {
        $sync = "def stream(self):\n    with guard():\n        for part in self._parts:\n            yield part.decode('utf-8')\n";
        $async = "async def stream(self):\n    async with guard():\n        async for part in self._parts:\n            yield part.decode('utf-8')\n";

        $this->assertSame([], $this->findIn(Codebase::fromString("{$sync}\n\n{$async}")));
    }

    /**
     * @return list<string>
     */
    private function findIn(Codebase $codebase): array
    {
        return array_map(static fn (NodeMatch $match): string => $match->scope(), new DuplicateFunctionDetector()->find($codebase));
    }
}
