<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Dashboard;

use JesseGall\CodeCommandments\Cli\Dashboard\FindingsStore;
use JesseGall\CodeCommandments\Finding;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

final class SinsDashboardTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = (string) realpath(sys_get_temp_dir()) . '/cc-dashboard-' . uniqid();
        mkdir("{$this->root}/src", 0777, true);
        mkdir("{$this->root}/.journal/plugins/code-commandments/.journal-plugin", 0777, true);
        file_put_contents("{$this->root}/.journal/plugins/code-commandments/.journal-plugin/plugin.json", '{}');
        touch("{$this->root}/src/Order.php");
        touch("{$this->root}/src/Cart.php");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_the_dashboard_counts_the_sins_and_opens_a_page_per_sin_and_file(): void
    {
        $this->store()->record([
            $this->finding('array-bag', 'src/Order.php', 12),
            $this->finding('array-bag', 'src/Order.php', 30),
            $this->finding('array-bag', 'src/Cart.php', 4),
            $this->finding('deep-nesting', 'src/Cart.php', 9),
        ], null);

        $dashboard = $this->dashboard();
        $overview = $dashboard['pages']['overview']['view']['children'];

        $this->assertSame('overview', $dashboard['start']);
        $this->assertSame(['4', '2', '2'], array_column($overview[0]['children'], 'value'), 'sins, files, skills');
        $this->assertSame([['array-bag', 3, 'sin/array-bag'], ['deep-nesting', 1, 'sin/deep-nesting']], array_map(static fn (array $bar): array => [$bar['label'], $bar['value'], $bar['open']], $overview[1]['items']));
        $sinPage = $dashboard['pages']['sin/array-bag']['view']['children'];
        $this->assertSame([['src/Order.php', '2'], ['src/Cart.php', '1']], array_column($sinPage[3]['rows'], 'cells'));
        $this->assertSame(['What it is', 'The rule', 'How to fix it'], array_column($sinPage[1]['children'], 'label'));
        $this->assertSame('commandments info array-bag', $sinPage[2]['text']);
        $this->assertSame([12, 30], array_column($dashboard['pages']['sin/array-bag/src/Order.php']['view']['children'][1]['children'], 'line'));
    }

    public function test_a_scoped_run_replaces_only_the_files_it_judged(): void
    {
        $this->store()->record([$this->finding('array-bag', 'src/Order.php', 12), $this->finding('deep-nesting', 'src/Cart.php', 9)], null);
        $this->store()->record([], ["{$this->root}/src/Order.php" => true]);

        $this->assertSame(['sin/deep-nesting'], array_column($this->dashboard()['pages']['overview']['view']['children'][1]['items'], 'open'));
    }

    private function store(): FindingsStore
    {
        return new FindingsStore(new Workspace($this->root));
    }

    private function finding(string $sin, string $file, int $line): Finding
    {
        return new Finding('Detector', "skill-of-{$sin}", $sin, "{$this->root}/{$file}", "{$this->root}/{$file}:{$line}", 'Order::total');
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboard(): array
    {
        return json_decode((string) file_get_contents("{$this->root}/.journal/plugin-data/code-commandments/dashboards/sins.json"), true);
    }
}
