<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\RaiseWithoutCauseDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class RaiseWithoutCauseDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new RaiseWithoutCauseDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'a named handler' => ["def a(raw):\n    try:\n        return int(raw)\n    except ValueError as error:\n        raise BadQuantity.of(raw)\n"];
        yield 'an unnamed handler' => ["def a(rows, key):\n    try:\n        return rows[key]\n    except KeyError:\n        raise MissingRow(key)\n"];
        yield 'deep in the handler' => ["def a(x):\n    try:\n        go(x)\n    except OSError as e:\n        if x.retry:\n            raise Retry()\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'chained' => ["def a(raw):\n    try:\n        return int(raw)\n    except ValueError as error:\n        raise BadQuantity.of(raw) from error\n"];
        yield 'deliberately cut' => ["def a(raw):\n    try:\n        return int(raw)\n    except ValueError:\n        raise BadQuantity.of(raw) from None\n"];
        yield 'a bare re-raise' => ["def a(x):\n    try:\n        go(x)\n    except OSError:\n        log(x)\n        raise\n"];
        yield 'the caught one again' => ["def a(x):\n    try:\n        go(x)\n    except OSError as e:\n        log(e)\n        raise e\n"];
        yield 'outside any handler' => ["def a(x):\n    if not x:\n        raise Missing()\n"];
        yield 'in a function the handler defines' => ["def a(x):\n    try:\n        go(x)\n    except OSError:\n        def later():\n            raise Retry()\n        return later\n"];
        yield 'the cause set by hand' => ["def a(x):\n    try:\n        go(x)\n    except OSError as e:\n        wrapped = Retry()\n        wrapped.__cause__ = e\n        raise wrapped\n"];
        yield 'in the try body' => ["def a(x):\n    try:\n        raise Missing()\n    except OSError as e:\n        raise Retry() from e\n"];
    }
}
