<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Exemptions;
use PHPUnit\Framework\TestCase;

/**
 * The `exemptions` command reads each detector's own {@see \JesseGall\CodeCommandments\Packages\Exemptable}
 * declaration, so the list can never drift from what a detector actually honours.
 */
final class ExemptionsTest extends TestCase
{
    public function test_no_argument_lists_every_exemption_with_its_slug(): void
    {
        [$code, $out] = $this->exec([]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('boundary', $out);
        $this->assertStringContainsString('no-container', $out);
        $this->assertStringContainsString('contract-method', $out);
    }

    public function test_a_sin_id_lists_that_detectors_exemptions(): void
    {
        [$code, $out] = $this->exec(['array-bag']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('ArrayBagDetector', $out);
        $this->assertStringContainsString('no-container', $out);
        $this->assertStringNotContainsString('boundary', $out);
    }

    public function test_an_unknown_query_fails(): void
    {
        [$code] = $this->exec(['no-such-sin']);

        $this->assertSame(2, $code);
    }

    /**
     * @param  list<string>  $args
     * @return array{int, string}
     */
    private function exec(array $args): array
    {
        ob_start();
        $code = new Exemptions()->run($args);

        return [$code, (string) ob_get_clean()];
    }
}
