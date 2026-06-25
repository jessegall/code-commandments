<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Commandments\BaseCommandment;
use JesseGall\CodeCommandments\Contracts\Commandment;
use JesseGall\CodeCommandments\Support\RootCauseMap;
use PHPUnit\Framework\TestCase;

/**
 * Self-test: the RootCauseMap stays internally consistent and in lock-step with
 * the prophets' derived `supersedes()` / `rootCauses()`. Fails the moment an
 * edge references a non-prophet, or a hand-override desyncs the two directions —
 * so the family stays correct as prophets are added.
 */
class RootCauseMapTest extends TestCase
{
    public function test_every_mapped_class_is_a_real_commandment(): void
    {
        foreach ($this->allMappedClasses() as $class) {
            $this->assertTrue(class_exists($class), "Mapped class {$class} does not exist");
            $this->assertTrue(
                is_subclass_of($class, BaseCommandment::class),
                "Mapped class {$class} is not a BaseCommandment",
            );
        }
    }

    public function test_causes_and_symptoms_are_exact_inverses_of_relations(): void
    {
        $relations = RootCauseMap::relations();

        // symptomsOf is just the forward lookup.
        foreach ($relations as $cause => $symptoms) {
            $this->assertSame($symptoms, RootCauseMap::symptomsOf($cause));
        }

        // causesOf is the exact flip: cause ∈ causesOf(symptom) ⇔ symptom ∈ relations[cause].
        foreach ($relations as $cause => $symptoms) {
            foreach ($symptoms as $symptom) {
                $this->assertContains(
                    $cause,
                    RootCauseMap::causesOf($symptom),
                    "causesOf({$symptom}) should contain {$cause}",
                );
            }
        }

        foreach ($relations as $cause => $symptoms) {
            foreach ($symptoms as $symptom) {
                foreach (RootCauseMap::causesOf($symptom) as $resolvedCause) {
                    $this->assertArrayHasKey($resolvedCause, $relations);
                    $this->assertContains(
                        $symptom,
                        $relations[$resolvedCause],
                        "{$resolvedCause} is listed as a cause of {$symptom} but does not declare it",
                    );
                }
            }
        }
    }

    public function test_prophets_derive_both_directions_from_the_map(): void
    {
        foreach (RootCauseMap::relations() as $cause => $symptoms) {
            /** @var Commandment $causeProphet */
            $causeProphet = new $cause();

            foreach ($symptoms as $symptom) {
                $this->assertContains(
                    $symptom,
                    $causeProphet->supersedes(),
                    "{$cause}::supersedes() should contain {$symptom} (from the map)",
                );

                /** @var Commandment $symptomProphet */
                $symptomProphet = new $symptom();
                $this->assertContains(
                    $cause,
                    $symptomProphet->rootCauses(),
                    "{$symptom}::rootCauses() should contain {$cause} (from the map)",
                );
            }
        }
    }

    /**
     * @return list<class-string>
     */
    private function allMappedClasses(): array
    {
        $classes = [];

        foreach (RootCauseMap::relations() as $cause => $symptoms) {
            $classes[$cause] = true;

            foreach ($symptoms as $symptom) {
                $classes[$symptom] = true;
            }
        }

        return array_keys($classes);
    }
}
