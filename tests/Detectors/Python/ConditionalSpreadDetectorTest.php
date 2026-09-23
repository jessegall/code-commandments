<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\ConditionalSpreadDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class ConditionalSpreadDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new ConditionalSpreadDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'a dict key included when set' => ["payload = {'id': 1, **({'note': note} if note else {})}\n"];
        yield 'a list item included when set' => ["args = [cmd, *([flag] if flag else [])]\n"];
        yield 'keyword arguments' => ["send(to, **({'cc': cc} if cc is not None else {}))\n"];
        yield 'the empty side first' => ["row = {**base, **({} if hidden else {'shown': True})}\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a plain spread' => ["payload = {**base, 'id': 1}\n"];
        yield 'two real choices' => ["row = {**(full if wide else narrow)}\n"];
        yield 'a conditional value' => ["payload = {'note': note if note else None}\n"];
        yield 'a type normalised' => ["got = {**(raw if isinstance(raw, dict) else {}), **fields}\n"];
        yield 'a computed list or none' => ["parts = [*(text.split('; ') if text else []), change]\n"];
    }
}
