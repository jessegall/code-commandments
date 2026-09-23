<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Frontend\TypeScript;

use JesseGall\CodeCommandments\Detectors\Frontend\TypeScript\DuplicateFunctionDetector;
use JesseGall\CodeCommandments\Ts\NodeMatch;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

final class DuplicateFunctionDetectorTest extends TestCase
{
    private const string LOAD = <<<'TS'
        {
            const response = await http.get(`/orders?page=${page}`);
            if (response.status !== 200) {
                throw new Error(response.statusText);
            }
            return response.data.items.map((item) => item.id);
        }
        TS;

    public function test_flags_a_function_and_a_const_arrow_sharing_one_body_under_different_names(): void
    {
        $found = $this->findIn(Codebase::fromTypeScript(
            'async function loadOrders(page: number) ' . self::LOAD . "\n"
            . 'const fetchOrderIds = async (page: number) => ' . self::LOAD . ';',
        ));

        $this->assertSame(['FunctionDecl loadOrders', 'VariableDecl fetchOrderIds'], $found);
    }

    public function test_flags_a_copy_across_a_component_script_and_a_module(): void
    {
        $root = sys_get_temp_dir() . '/dup-fn-' . uniqid();
        mkdir($root);
        file_put_contents("{$root}/orders.ts", 'export async function loadOrders(page: number) ' . self::LOAD);
        file_put_contents("{$root}/OrderList.vue", "<script setup lang=\"ts\">\nconst load = async (page: number) => " . self::LOAD . ";\n</script>\n<template><ul></ul></template>\n");

        $found = $this->findIn(Codebase::scan($root));

        array_map('unlink', glob("{$root}/*") ?: []);
        rmdir($root);

        $this->assertCount(2, $found);
    }

    public function test_formatting_and_comments_do_not_hide_a_copy(): void
    {
        $reformatted = <<<'TS'
            {
                // the page of orders
                const response = await http.get(`/orders?page=${page}`);
                if (response.status !== 200) { throw new Error(response.statusText); }
                return response.data.items.map(item => item.id);
            }
            TS;

        $found = $this->findIn(Codebase::fromTypeScript(
            'async function a(page: number) ' . self::LOAD . "\nasync function b(page: number) " . $reformatted,
        ));

        $this->assertCount(2, $found);
    }

    public function test_leaves_bodies_that_differ_in_a_literal(): void
    {
        $customers = str_replace('/orders', '/customers', self::LOAD);

        $this->assertSame([], $this->findIn(Codebase::fromTypeScript(
            'async function a(page: number) ' . self::LOAD . "\nasync function b(page: number) " . $customers,
        )));
    }

    public function test_leaves_short_bodies_that_are_alike_by_coincidence(): void
    {
        $this->assertSame([], $this->findIn(Codebase::fromTypeScript(
            "function a(o: Order) { return o.total; }\nfunction b(o: Order) { return o.total; }",
        )));
    }

    public function test_a_single_function_is_not_a_duplicate(): void
    {
        $this->assertSame([], $this->findIn(Codebase::fromTypeScript('async function a(page: number) ' . self::LOAD)));
    }

    /**
     * @return list<string>
     */
    private function findIn(Codebase $codebase): array
    {
        return array_map(static fn (NodeMatch $match): string => $match->scope(), new DuplicateFunctionDetector()->find($codebase));
    }
}
