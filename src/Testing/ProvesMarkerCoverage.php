<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

/**
 * The coverage a fixture proves beyond its sins: every rule shows the resolution its skill publishes
 * as Good (`@fixed`) and a look-alike it must leave alone (`@righteous`). Opt-in on a
 * {@see FixtureTestCase}, one mechanism for every engine's fixture.
 */
trait ProvesMarkerCoverage
{
    abstract protected function fixture(): EngineFixture;

    public function test_every_sin_carries_a_resolution(): void
    {
        $this->assertSame(
            [],
            $this->fixture()->withoutMarker('fixed'),
            "These rules have no @fixed resolution, so their published 'good' example falls back to a\n"
            . "righteous look-alike — code that legitimately DODGES the rule rather than obeying it.\n"
            . 'Add the fix to the fixture; see the `detector-fixtures` skill.',
        );
    }

    public function test_every_example_file_is_proven(): void
    {
        $this->assertSame([], $this->fixture()->unprovenExamples(), 'An @example file is published as the rule\'s own Bad or Good, so the fixture must prove it.');
    }

    public function test_every_detector_has_a_righteous_twin(): void
    {
        $this->assertSame([], $this->fixture()->withoutMarker('righteous'), 'These rules have no @righteous twin — add one good example of what they must leave alone.');
    }
}
