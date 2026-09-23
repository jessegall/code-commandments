<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\PhantomNullableDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

final class PhantomNullableDetectorTest extends TestCase
{
    public function test_flags_an_optional_field_every_read_assumes_is_there(): void
    {
        $this->assertSame([6, 17], $this->lines(<<<'PY'
            class Clock:
                def now(self) -> int:
                    return 0

            class Timer:
                clock: Clock | None = None

                def elapsed(self, since: int) -> int:
                    return self.clock.now() - since

                def stamp(self) -> str:
                    return str(self.clock.now())


            class Report:
                def __init__(self, title: str | None = None) -> None:
                    self.title = title

                def heading(self) -> str:
                    return self.title.upper()
            PY));
    }

    public function test_leaves_a_guarded_field_and_one_set_back_to_none(): void
    {
        $this->assertSame([], $this->lines(<<<'PY'
            class Session:
                def __init__(self, user: str | None = None, token: str | None = None) -> None:
                    self.user = user
                    self.token = token

                def greeting(self) -> str:
                    if self.user is None:
                        return "hello"
                    return self.user.title()

                def header(self) -> str:
                    return self.token.strip()

                def logout(self) -> None:
                    self.token = None


            def encode(content: str | None) -> str:
                return content or ""


            class Upload:
                def __init__(self, stream: str | None = None, name: str | None = None) -> None:
                    stream = encode(stream)
                    self.stream = stream
                    if name is not None:
                        self.name = name

                def size(self) -> int:
                    return len(self.stream.strip()) + len(self.name.strip())
            PY));
    }

    /**
     * @return list<int>
     */
    private function lines(string $source): array
    {
        return array_map(static fn (NodeMatch $match): int => $match->line(), new PhantomNullableDetector()->find(Codebase::fromString($source)));
    }
}
