<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\NearDuplicateFunctionDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

final class NearDuplicateFunctionDetectorTest extends TestCase
{
    private const string ENCODE = <<<'PY'
            body = text.encode("utf-8")
            length = str(len(body))
            kind = "text/plain; charset=utf-8"
            headers = {"Content-Length": length, "Content-Type": kind}
            return headers, ByteStream(body)
        PY;

    public function test_flags_two_bodies_that_differ_only_in_a_literal_and_their_names(): void
    {
        $html = str_replace(['text/plain', 'text.encode'], ['text/html', 'html.encode'], self::ENCODE);

        $found = $this->findIn(Codebase::fromString("def encode_text(text):\n" . self::ENCODE . "\n\ndef encode_html(html):\n{$html}\n"));

        $this->assertSame(['FunctionDef encode_text', 'FunctionDef encode_html'], $found);
    }

    public function test_leaves_byte_identical_copies_to_the_exact_rule(): void
    {
        $this->assertSame([], $this->findIn(Codebase::fromString("def a(text):\n" . self::ENCODE . "\n\ndef b(text):\n" . self::ENCODE . "\n")));
    }

    public function test_a_different_call_is_different_code(): void
    {
        $gzip = str_replace('ByteStream(body)', 'GzipStream(body)', self::ENCODE);

        $this->assertSame([], $this->findIn(Codebase::fromString("def a(text):\n" . self::ENCODE . "\n\ndef b(text):\n{$gzip}\n")));
    }

    public function test_leaves_lookup_tables_and_stubs_alone(): void
    {
        $table = "def colour(status):\n    if status == 'paid':\n        return 'green'\n    if status == 'late':\n        return 'red'\n    if status == 'void':\n        return 'grey'\n    return None\n";
        $stub = "    def handle(self, event, context, options):\n        \"\"\"Handle the event: subclasses decide what that means for them.\"\"\"\n        raise NotImplementedError(f'{type(self).__name__} handles {event.kind} in {context.name} as {options.mode} for {self.owner.name}')\n";

        $this->assertSame([], $this->findIn(Codebase::fromString($table . "\n\n" . str_replace(['colour', 'green', 'red'], ['badge', 'ok', 'warn'], $table))));
        $this->assertSame([], $this->findIn(Codebase::fromString("class A:\n{$stub}\n\nclass B:\n" . str_replace('handles', 'processes', $stub))));
    }

    public function test_leaves_two_constructors_alone(): void
    {
        $init = "    def __init__(self, source, started, label):\n        self._source = source\n        self._started = started\n        self._label = label\n        self._closed = False\n        self._name = 'feed'\n";

        $this->assertSame([], $this->findIn(Codebase::fromString("class A:\n{$init}\n\nclass B:\n" . str_replace("'feed'", "'stream'", $init))));
    }

    /**
     * @return list<string>
     */
    private function findIn(Codebase $codebase): array
    {
        return array_map(static fn (NodeMatch $match): string => $match->scope(), new NearDuplicateFunctionDetector()->find($codebase));
    }
}
