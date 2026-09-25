package backend

import (
	"strings"
	"testing"

	rules "github.com/jessegall/code-commandments/detectors/backend"
)

var wrappingWithoutCause = scribeCase{rules.WrappingWithoutCauseDetector{}, WrappingWithoutCauseScribe{}}

func TestWrappingWithoutCausePassesTheCaughtExceptionAsThePreviousCause(t *testing.T) {
	source := `<?php

class Loader
{
    public function load(): void
    {
        try {
            $this->risky();
        } catch (\RuntimeException $e) {
            throw new LoadFailed('could not load');
        }
    }
}`

	fixed := wrappingWithoutCause.fixStable(t, source)

	if !strings.Contains(fixed, `throw new LoadFailed('could not load', previous: $e);`) {
		t.Fatalf("%s", fixed)
	}
}

func TestWrappingWithoutCauseAddsTheCauseEvenWithNoExistingArguments(t *testing.T) {
	source := `<?php

class Loader
{
    public function load(): void
    {
        try {
            $this->risky();
        } catch (\Throwable $error) {
            throw new LoadFailed();
        }
    }
}`

	fixed := wrappingWithoutCause.fixStable(t, source)

	if !strings.Contains(fixed, `throw new LoadFailed(previous: $error);`) {
		t.Fatalf("%s", fixed)
	}
}

func TestWrappingWithoutCauseDoesNotOvershootAThrowThatAlreadyForwardsTheCause(t *testing.T) {
	// The cause is already passed → not flagged; a bare throw outside a catch → not flagged.
	source := `<?php

class Loader
{
    public function load(): void
    {
        try {
            $this->risky();
        } catch (\RuntimeException $e) {
            throw new LoadFailed('nope', previous: $e);
        }
    }

    public function guard(int $n): void
    {
        if ($n < 0) {
            throw new InvalidInput('negative');
        }
    }
}`

	if found := wrappingWithoutCause.findings(t, source); len(found) != 0 {
		t.Fatalf("%s", "the sin still fires")
	}
	if got := wrappingWithoutCause.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}
