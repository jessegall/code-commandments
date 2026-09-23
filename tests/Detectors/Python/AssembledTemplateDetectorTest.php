<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\AssembledTemplateDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class AssembledTemplateDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new AssembledTemplateDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'a block of fixed lines' => ["def render(name):\n    return \"\\n\".join([\"def main():\", f\"    greet({name!r})\", \"    return 0\"])\n"];
        yield 'a tuple of lines' => ["def header(title, owner):\n    return \"\\n\".join((\"---\", f\"title: {title}\", f\"owner: {owner}\", \"---\"))\n"];
        yield 'single-quoted newline' => ["def block(body):\n    return '\\n'.join(['<div>', body, '</div>'])\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a computed list' => ["def lines(findings):\n    return \"\\n\".join(f.label for f in findings)\n"];
        yield 'a named list' => ["def lines(rows):\n    return \"\\n\".join(rows)\n"];
        yield 'a pair' => ["def pair(a):\n    return \"\\n\".join([\"head\", a])\n"];
        yield 'mostly computed' => ["def list3(a, b, c):\n    return \"\\n\".join([a, b, \"end\"])\n"];
        yield 'one line' => ["def cols(a, b, c):\n    return \", \".join([\"id\", \"name\", \"email\"])\n"];
    }
}
