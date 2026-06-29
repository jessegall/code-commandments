<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\KeyedLookupEnvyDetector;
use PHPUnit\Framework\TestCase;

final class KeyedLookupEnvyDetectorTest extends TestCase
{
    public function test_flags_a_method_that_keys_into_a_collaborator_by_the_param(): void
    {
        $code = <<<'PHP'
        <?php
        final class Node { public string $key = ''; }
        final class Reservations {
            public function __construct(private readonly object $registry) {}
            public function forNode(Node $node): array {
                return $this->registry->get($node->key)->reservedNames;
            }
        }
        PHP;

        $hits = (new KeyedLookupEnvyDetector)->find(Codebase::fromString($code));

        $this->assertSame(['Reservations::forNode'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_leaves_the_righteous_twins_alone(): void
    {
        $code = <<<'PHP'
        <?php
        final class Node { public string $key = ''; }
        final class Dto { public function __construct(public string $k) {} }
        // param passed WHOLE (delegation), not used as a key
        final class Delegator {
            public function __construct(private readonly object $svc) {}
            public function run(Node $node): array { return $this->svc->process($node)->rows; }
        }
        // CONSTRUCTS a value — a mapper/factory
        final class Mapper {
            public function __construct(private readonly object $registry) {}
            public function map(Node $node): Dto { return new Dto($this->registry->get($node->key)->name); }
        }
        // returns an OBJECT / action, not a fact
        final class Downloader {
            public function __construct(private readonly object $disks) {}
            public function fetch(Node $node): object { return $this->disks->find($node->key)->stream(); }
        }
        // reads its OWN data, no collaborator lookup
        final class SelfReader {
            public function label(Node $node): string { return strtoupper($node->key); }
        }
        PHP;

        $this->assertSame([], (new KeyedLookupEnvyDetector)->find(Codebase::fromString($code)));
    }
}
