<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\ConstructorSideEffectDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class ConstructorSideEffectDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new ConstructorSideEffectDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'a handed collaborator told to act' => ["class Export:\n    def __init__(self, client, period):\n        self.client = client\n        client.warm(period)\n"];
        yield 'through the field it was stored in' => ["class Export:\n    def __init__(self, client):\n        self.client = client\n        self.client.connect()\n"];
        yield 'registering itself' => ["class Plugin:\n    def __init__(self, registry):\n        registry.add(self)\n"];
        yield 'a fluent run on a collaborator' => ["class Report:\n    def __init__(self, query):\n        query.where('open').limit(10)\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'asked for something it keeps' => ["class Export:\n    def __init__(self, client):\n        self.rows = client.fetch()\n"];
        yield 'its own helper' => ["class Export:\n    def __init__(self, client):\n        self.client = client\n        self._prepare()\n"];
        yield 'its own list being filled' => ["class Bag:\n    def __init__(self, items):\n        self.items = []\n        self.items.extend(items)\n"];
        yield 'the parent constructor' => ["class Export(Base):\n    def __init__(self, client):\n        super().__init__(client)\n"];
        yield 'a plain function' => ["class Export:\n    def __init__(self, path):\n        self.path = path\n        validate(path)\n"];
        yield 'its own keyword arguments' => ["class Group:\n    def __init__(self, **extra):\n        extra.setdefault('help', None)\n        self.extra = extra\n"];
        yield 'a parent initialised by name beside a keyed write' => ["class Env(Base):\n    def __init__(self, app, **options):\n        options['loader'] = app.loader()\n        Base.__init__(self, **options)\n"];
        yield 'a field it also builds itself' => ["class Cookies:\n    def __init__(self, cookies=None):\n        if cookies is None:\n            self.jar = Jar()\n            self.jar.clear()\n        else:\n            self.jar = cookies\n"];
        yield 'a probe for failure' => ["class Charset:\n    def __init__(self, name):\n        try:\n            name.encode('ascii')\n        except UnicodeError:\n            raise BadCharset(name) from None\n        self.name = name\n"];
        yield 'a method outside the constructor' => ["class Export:\n    def __init__(self, client):\n        self.client = client\n\n    def run(self):\n        self.client.connect()\n"];
    }
}
