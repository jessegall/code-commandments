<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\ResourceReach;
use PHPUnit\Framework\TestCase;

final class ScopeReachTest extends TestCase
{
    public function test_a_signature_is_not_counted_as_work_the_body_does(): void
    {
        $reach = $this->reachOf(<<<'PHP'
        <?php
        namespace App;
        class Reporter {
            public function record(Alpha $a, Beta $b, #[Gamma] Delta $d): Epsilon {
                return \buildIt($a);
            }
        }
        PHP);

        $scope = array_keys($reach->scopes()->of('App\Reporter::record'));

        // Everything the signature names — parameter types, a parameter attribute, the return type —
        // says what the method ACCEPTS, never what it does.
        $this->assertNotContains('App\Alpha', $scope);
        $this->assertNotContains('App\Beta', $scope);
        $this->assertNotContains('App\Gamma', $scope);
        $this->assertNotContains('App\Delta', $scope);
        $this->assertNotContains('App\Epsilon', $scope);

        $this->assertContains('fn:buildIt', $scope);
    }

    public function test_the_class_population_still_sees_its_collaborators(): void
    {
        $reach = $this->reachOf(<<<'PHP'
        <?php
        namespace App;
        class Reporter {
            public function record(Alpha $a): Epsilon { return \buildIt($a); }
        }
        PHP);

        // A CLASS genuinely collaborates with the types its methods accept — only the per-scope
        // reading, which asks what a body DOES, leaves them out.
        $class = array_keys($reach->classes()->of('App\Reporter'));

        $this->assertContains('App\Alpha', $class);
        $this->assertContains('App\Epsilon', $class);
    }

    public function test_a_body_reference_to_the_same_type_still_counts(): void
    {
        $reach = $this->reachOf(<<<'PHP'
        <?php
        namespace App;
        class Reporter {
            public function record(Alpha $a): void { Alpha::announce(); }
        }
        PHP);

        $this->assertContains('App\Alpha', array_keys($reach->scopes()->of('App\Reporter::record')));
    }

    private function reachOf(string $code): ResourceReach
    {
        return ResourceReach::forCodebase(Codebase::fromString($code));
    }
}
