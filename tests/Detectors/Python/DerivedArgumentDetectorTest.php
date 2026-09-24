<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\DerivedArgumentDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Support\Directory;
use JesseGall\CodeCommandments\Tests\Py\NeedsTheTypeBridge;
use PHPUnit\Framework\TestCase;

final class DerivedArgumentDetectorTest extends TestCase
{
    use NeedsTheTypeBridge;

    public function test_flags_every_call_that_hands_over_an_object_and_a_projection_of_it(): void
    {
        $this->assertSame([10, 14], $this->lines(<<<'PY'
            class Request:
                channel_id: str


            def persist(request: Request, channel: str) -> None:
                pass


            def save(request: Request) -> None:
                persist(request, request.channel_id)


            def retry(request: Request) -> None:
                persist(request, channel=request.channel_id)
            PY));
    }

    public function test_flags_a_typed_object_handed_over_in_three_pieces(): void
    {
        $this->assertSame(['shop/finish.py:5'], $this->scanned([
            'shop/result.py' => "class Result:\n    error: str = ''\n\n    def output(self) -> str:\n        return ''\n\n    def failed(self) -> bool:\n        return False\n",
            'shop/finish.py' => "from shop.result import Result\nfrom shop.record import record\n\ndef finish(result: Result) -> None:\n    record(result.output(), result.failed(), result.error)\n",
            'shop/record.py' => "def record(output: str, failed: bool, error: str) -> None:\n    pass\n",
        ]));
    }

    public function test_leaves_pieces_whose_whole_would_close_an_import_cycle(): void
    {
        $this->assertSame([], $this->scanned([
            'engine/pages.py' => "import commands.tool\n\ndef page(since: int, before: int, last: int) -> list[int]:\n    return []\n",
            'commands/query.py' => "from engine.pages import page\n\nclass Query:\n    since: int = 0\n    before: int = 0\n    last: int = 0\n\ndef show(asked: Query) -> list[int]:\n    return page(asked.since, asked.before, asked.last)\n",
            'commands/tool.py' => "VERSION = 1\n",
        ]));
    }

    public function test_leaves_a_slot_another_caller_fills_from_elsewhere_and_two_pieces_alone(): void
    {
        $this->assertSame([], $this->lines(<<<'PY'
            class Request:
                channel_id: str
                name: str


            def persist(request: Request, channel: str) -> None: ...

            def header(title: str, subtitle: str) -> str: ...

            def save(request: Request) -> None:
                persist(request, request.channel_id)


            def migrate(request: Request, channel: str) -> None:
                persist(request, channel)


            def show(request: Request) -> str:
                return header(request.name, request.channel_id)
            PY));
    }

    public function test_leaves_a_class_building_itself_from_its_parts(): void
    {
        $this->assertSame([], $this->lines(<<<'PY'
            class Row:
                label: str
                count: int
                total: int


            class Summary:
                def __init__(self, label: str, count: int, total: int) -> None:
                    self.label = label

                @classmethod
                def of(cls, row: Row) -> "Summary":
                    return cls(row.label, row.count, row.total)
            PY));
    }

    /**
     * The findings over a project written to disk, typed by mypy, as `path:line` under its root.
     *
     * @param  array<string, string>  $files
     * @return list<string>
     */
    private function scanned(array $files): array
    {
        $this->requireTheTypeBridge();

        $root = sys_get_temp_dir() . '/derived-arg-' . bin2hex(random_bytes(4));

        foreach ($files as $path => $source) {
            @mkdir(dirname("{$root}/{$path}"), 0777, true);
            touch(dirname("{$root}/{$path}") . '/__init__.py');
            file_put_contents("{$root}/{$path}", $source);
        }

        try {
            return array_map(static fn (ExprMatch $match): string => substr($match->location(), strlen($root) + 1), new DerivedArgumentDetector()->find(Codebase::scan($root)));
        } finally {
            Directory::delete($root);
        }
    }

    /**
     * @return list<int>
     */
    private function lines(string $source): array
    {
        return array_map(static fn (ExprMatch $match): int => $match->line(), new DerivedArgumentDetector()->find(Codebase::fromString($source)));
    }
}
