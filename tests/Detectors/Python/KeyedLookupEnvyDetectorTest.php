<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\KeyedLookupEnvyDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

final class KeyedLookupEnvyDetectorTest extends TestCase
{
    private const string MODEL = <<<'PY'
        class Spec:
            reserved: list[str]
            label: str


        class Node:
            key: str
            title: str


        class Registry:
            def get(self, key: str) -> Spec:
                return Spec()

        PY;

    public function test_flags_a_fact_fetched_by_the_objects_own_key_through_a_collaborator(): void
    {
        $this->assertSame(['reserved_names', 'label_of'], $this->flagged(<<<'PY'
            class Ports:
                def __init__(self, registry: Registry, specs: dict[str, Spec]) -> None:
                    self.registry = registry
                    self.specs = specs

                def reserved_names(self, node: Node) -> list[str]:
                    return self.registry.get(node.key).reserved

                def label_of(self, node: Node) -> str:
                    return self.specs[node.key].label
            PY));
    }

    public function test_leaves_its_own_lookup_a_handed_off_object_and_a_built_value(): void
    {
        $this->assertSame([], $this->flagged(<<<'PY'
            class Ports:
                def __init__(self, registry: Registry) -> None:
                    self.registry = registry

                def find(self, key: str) -> Spec:
                    return self.registry.get(key)

                def own(self, node: Node) -> list[str]:
                    return self.find(node.key).reserved

                def handed(self, node: Node) -> str:
                    print(node)
                    return self.registry.get(node.key).label

                def built(self, node: Node) -> Spec:
                    return Spec()
            PY));
    }

    /**
     * @return list<string>
     */
    private function flagged(string $classes): array
    {
        return array_map(static fn (NodeMatch $match): string => $match->name(), new KeyedLookupEnvyDetector()->find(Codebase::fromString(self::MODEL . "\n\n" . $classes)));
    }
}
