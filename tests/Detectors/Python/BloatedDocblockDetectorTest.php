<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\BloatedDocblockDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class BloatedDocblockDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new BloatedDocblockDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'two paragraphs of prose' => ["class Cart:\n    \"\"\"The lines a customer means to buy.\n\n    It also prices them, applies vouchers and talks to the warehouse.\n    \"\"\"\n\n    lines = []\n"];
        yield 'an essay before its sections' => ["class Router:\n    \"\"\"Routes requests.\n\n    Matching happens in order of registration.\n\n    Args:\n        rules: the rules.\n    \"\"\"\n\n    rules = []\n"];
        yield 'a bullet list after the lead' => ["class Settings:\n    \"\"\"Holds the shop's settings.\n\n    - currency\n    - tax region\n    \"\"\"\n\n    currency = 'EUR'\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'one paragraph' => ["class Cart:\n    \"\"\"The lines a customer means to buy,\n    priced at the moment they were added.\n    \"\"\"\n\n    lines = []\n"];
        yield 'one paragraph and its sections' => ["class Cart:\n    \"\"\"The lines a customer means to buy.\n\n    Attributes:\n        lines: every line.\n\n    Example:\n        >>> Cart().lines\n        []\n    \"\"\"\n\n    lines = []\n"];
        yield 'one paragraph and NumPy parameters' => ["class Grid:\n    \"\"\"A grid of cells.\n\n    Parameters\n    ----------\n    width : int\n        How wide.\n    \"\"\"\n\n    width = 0\n"];
        yield 'one paragraph and a version note' => ["class Cart:\n    \"\"\"The lines a customer means to buy.\n\n    .. versionadded:: 2.0\n    \"\"\"\n\n    lines = []\n"];
        yield 'one paragraph and Sphinx fields' => ["class Provider:\n    \"\"\"A set of JSON operations.\n\n    :param app: the application.\n\n    .. code-block:: python\n\n        Provider(app)\n    \"\"\"\n\n    app = None\n"];
        yield 'a function docstring' => ["def price(order):\n    \"\"\"The total.\n\n    Tax included.\n    \"\"\"\n    return order.total\n"];
    }
}
