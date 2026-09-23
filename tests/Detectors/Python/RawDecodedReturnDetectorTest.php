<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\RawDecodedReturnDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class RawDecodedReturnDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new RawDecodedReturnDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'loads' => ["import json\n\ndef settings(path):\n    return json.loads(path.read_text())\n"];
        yield 'load from a file' => ["import json\n\ndef manifest(root):\n    with open(root / 'm.json') as f:\n        return json.load(f)\n"];
        yield 'a method' => ["import json\n\nclass Client:\n    def order(self, n):\n        return json.loads(self.http.get(f'/orders/{n}'))\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'parsed into a type' => ["import json\n\ndef settings(path):\n    return Settings(**json.loads(path.read_text()))\n"];
        yield 'a round trip of our own value' => ["import json\n\ndef clone(data):\n    return json.loads(json.dumps(data))\n"];
        yield 'not returned' => ["import json\n\ndef count(path):\n    data = json.loads(path.read_text())\n    return len(data)\n"];
        yield 'a TypedDict return' => ["import json\n\nclass Config(TypedDict):\n    name: str\n\ndef config(path) -> Config:\n    return json.loads(path.read_text())\n"];
    }
}
