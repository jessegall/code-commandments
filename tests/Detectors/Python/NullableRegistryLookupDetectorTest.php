<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\NullableRegistryLookupDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use PHPUnit\Framework\TestCase;

final class NullableRegistryLookupDetectorTest extends TestCase
{
    public function test_flags_a_store_handing_back_none_for_a_key_it_lacks(): void
    {
        $this->assertSame([10, 13], $this->lines(<<<'PY'
            class Handler:
                pass


            class HandlerRegistry:
                def __init__(self) -> None:
                    self._handlers: dict[str, Handler] = {}

                def get(self, kind: str) -> Handler | None:
                    return self._handlers.get(kind)

                def lookup(self, kind: str) -> Handler | None:
                    return self._handlers.get(kind, None)
            PY));
    }

    public function test_leaves_a_raising_get_a_real_default_a_parameter_map_and_an_override(): void
    {
        $this->assertSame([], $this->lines(<<<'PY'
            class Base:
                def find(self, kind: str) -> str | None:
                    raise NotImplementedError


            class Names(Base):
                def __init__(self) -> None:
                    self._names: dict[str, str] = {}

                def get(self, kind: str) -> str:
                    return self._names[kind]

                def label(self, kind: str) -> str:
                    return self._names.get(kind, "unknown")

                def pick(self, options: dict[str, str], kind: str) -> str | None:
                    return options.get(kind)

                def find(self, kind: str) -> str | None:
                    return self._names.get(kind)
            PY));
    }

    /**
     * @return list<int>
     */
    private function lines(string $source): array
    {
        return array_map(static fn (ExprMatch $match): int => $match->line(), new NullableRegistryLookupDetector()->find(Codebase::fromString($source)));
    }
}
