<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\SwallowedExceptionDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class SwallowedExceptionDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new SwallowedExceptionDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'bare except, pass' => ["try:\n    load()\nexcept:\n    pass\n"];
        yield 'Exception, return None' => ["def a():\n    try:\n        return load()\n    except Exception:\n        return None\n"];
        yield 'BaseException in a tuple, return []' => ["def a():\n    try:\n        return load()\n    except (KeyError, BaseException):\n        return []\n"];
        yield 'Exception as e, continue' => ["for f in fs:\n    try:\n        f()\n    except Exception as e:\n        continue\n"];
        yield 'return False' => ["def a():\n    try:\n        check()\n    except Exception:\n        return False\n    return True\n"];
        yield 'ellipsis' => ["try:\n    load()\nexcept Exception:\n    ...\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a named failure, passed' => ["try:\n    os.remove(p)\nexcept FileNotFoundError:\n    pass\n"];
        yield 'broad but recorded' => ["try:\n    load()\nexcept Exception:\n    log.exception('load failed')\n"];
        yield 'broad and re-raised' => ["try:\n    load()\nexcept Exception as e:\n    raise LoadFailed.of(p) from e\n"];
        yield 'broad but returning a real value' => ["def a():\n    try:\n        return load()\n    except Exception:\n        return DEFAULTS\n"];
        yield 'two statements' => ["try:\n    load()\nexcept Exception:\n    count()\n    pass\n"];
    }
}
