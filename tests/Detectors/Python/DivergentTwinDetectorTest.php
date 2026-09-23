<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\DivergentTwinDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

final class DivergentTwinDetectorTest extends TestCase
{
    private const string TWINS = <<<'PY'
        import json
        import os
        import shutil


        def save_report(path: str, data: dict) -> None:
            os.makedirs(path, exist_ok=True)
            shutil.copy(path, path + ".bak")
            with open(path + ".tmp", "w") as handle:
                handle.write(json.dumps(data))
                os.fsync(handle.fileno())
            os.replace(path + ".tmp", path)


        def save_invoice(path: str, data: dict) -> None:
            os.makedirs(path, exist_ok=True)
            shutil.copy(path, path + ".bak")
            with open(path + ".tmp", "w") as handle:
                handle.write(json.dumps(data))
            os.replace(path + ".tmp", path)
        PY;

    public function test_flags_both_members_of_a_twin_where_one_does_strictly_less(): void
    {
        $this->assertSame(['save_invoice', 'save_report'], $this->flagged(self::TWINS));
    }

    public function test_leaves_twins_a_third_function_chooses_between(): void
    {
        $this->assertSame([], $this->flagged(self::TWINS . <<<'PY'


            def save(path: str, data: dict, invoice: bool) -> None:
                if invoice:
                    save_invoice(path, data)
                else:
                    save_report(path, data)
            PY));
    }

    /**
     * @return list<string>
     */
    private function flagged(string $source): array
    {
        $names = array_map(static fn (NodeMatch $match): string => $match->name(), new DivergentTwinDetector()->find(Codebase::fromString($source)));
        sort($names);

        return $names;
    }
}
