<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferInterfaceOverTypeListProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferInterfaceOverTypeListProphetTest extends TestCase
{
    private PreferInterfaceOverTypeListProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferInterfaceOverTypeListProphet();
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App;\nclass C {\n{$body}\n}\n");
    }

    public function test_flags_an_inline_type_name_list_membership_test(): void
    {
        $j = $this->judge("public function isData(string \$n): bool { return in_array(\$n, ['Bag', 'Collection', 'Data'], true); }");

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('hardcoded list of type names', $j->warnings[0]->message);
    }

    public function test_flags_a_const_type_name_list(): void
    {
        $j = $this->judge(
            "private const DATA_BASES = ['Bag', 'Collection', 'Data'];\n"
            . "public function isData(string \$n): bool { return in_array(\$n, self::DATA_BASES, true); }"
        );

        $this->assertCount(1, $j->warnings);
    }

    public function test_flags_namespaced_fqcn_list(): void
    {
        $j = $this->judge("public function f(\$x): bool { return in_array(\$x, ['App\\\\Foo\\\\Bag', 'App\\\\Foo\\\\Data']); }");

        $this->assertCount(1, $j->warnings);
    }

    public function test_leaves_non_type_value_lists(): void
    {
        // extensions, method names, lowercase predicates — legitimate data.
        $this->assertTrue($this->judge("public function a(\$x): bool { return in_array(\$x, ['php', 'js', 'ts']); }")->isRighteous());
        $this->assertTrue($this->judge("public function b(\$x): bool { return in_array(\$x, ['get', 'input', 'query']); }")->isRighteous());
        $this->assertTrue($this->judge("public function c(\$x): bool { return in_array(\$x, ['is_array', 'is_string']); }")->isRighteous());
    }

    public function test_leaves_an_associative_lookup_map(): void
    {
        // A class => value table is a lookup, not membership classification.
        $j = $this->judge(
            "private const MAP = ['Bag' => 'a', 'Collection' => 'b'];\n"
            . "public function f(\$x) { return in_array(\$x, self::MAP); }"
        );

        $this->assertTrue($j->isRighteous());
    }

    public function test_leaves_a_single_element_list(): void
    {
        $this->assertTrue($this->judge("public function f(\$x): bool { return in_array(\$x, ['Data']); }")->isRighteous());
    }
}
