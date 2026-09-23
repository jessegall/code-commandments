<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\ScratchStateRestoreDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class ScratchStateRestoreDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new ScratchStateRestoreDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'in a finally' => ["class Walker:\n    def visit(self, node, scope):\n        previous = self.scope\n        self.scope = scope\n        try:\n            return self.walk(node)\n        finally:\n            self.scope = previous\n"];
        yield 'straight through' => ["class Printer:\n    def indented(self, lines):\n        was = self.depth\n        self.depth = was + 1\n        out = [self.line(l) for l in lines]\n        self.depth = was\n        return out\n"];
        yield 'a nested attribute' => ["class Renderer:\n    def as_guest(self, page):\n        saved = self.ctx.user\n        self.ctx.user = None\n        html = self.render(page)\n        self.ctx.user = saved\n        return html\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a read kept, not restored' => ["class Walker:\n    def visit(self, node):\n        scope = self.scope\n        return self.walk(node, scope)\n"];
        yield 'restored from something else' => ["class Walker:\n    def reset(self):\n        previous = self.scope\n        self.scope = self.default\n        return previous\n"];
        yield 'read, changed and written back' => ["class Reader:\n    def left(self):\n        left = self.remaining\n        if not left:\n            left = self.next_chunk()\n        self.remaining = left\n        return left\n"];
        yield 'a context manager whose job it is' => ["class Shell:\n    @contextmanager\n    def quiet(self):\n        was = self.verbose\n        self.verbose = False\n        yield\n        self.verbose = was\n"];
        yield 'a parameter defaulted from the class' => ["class Profile:\n    bias = 0\n\n    def __init__(self, bias=None):\n        if bias is None:\n            bias = self.bias\n        self.bias = bias\n"];
        yield 'a local saved and restored' => ["def run(scope):\n    previous = scope\n    scope = 'x'\n    scope = previous\n    return scope\n"];
    }
}
