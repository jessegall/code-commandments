package backend

import (
	"strings"
	"testing"

	rules "github.com/jessegall/code-commandments/detectors/backend"
)

var redundantElse = scribeCase{rules.RedundantElseDetector{}, RedundantElseScribe{}}

func TestRedundantElseDropsTheElseAndHoistsItsBodyAfterTheGuard(t *testing.T) {
	source := `<?php

class Greeter
{
    public function greet(bool $known): string
    {
        if ($known) {
            return 'hi';
        } else {
            $msg = 'hello stranger';

            return $msg;
        }
    }
}`

	fixed := redundantElse.fixStable(t, source)

	// The guard is kept verbatim…
	if !strings.Contains(fixed, `if ($known) {
            return 'hi';
        }`) {
		t.Fatalf("%s", fixed)
	}
	// …the else wrapper is gone…
	if strings.Contains(fixed, `else`) {
		t.Fatalf("%s", fixed)
	}
	// …and its body is hoisted to the guard's level (dedented one step).
	if !strings.Contains(fixed, `        $msg = 'hello stranger';`) {
		t.Fatalf("%s", fixed)
	}
	if !strings.Contains(fixed, `        return $msg;`) {
		t.Fatalf("%s", fixed)
	}
}

func TestRedundantElseDoesNotOvershootAGenuineElse(t *testing.T) {
	// The first if/else is redundant (its if-branch returns); the second is a
	// genuine either/or (neither branch exits) and must be left byte-identical.
	source := `<?php

class Router
{
    public function pick(bool $a): string
    {
        if ($a) {
            return 'left';
        } else {
            return 'right';
        }
    }

    public function classify(int $n): string
    {
        $label = '';

        if ($n > 0) {
            $label = 'positive';
        } else {
            $label = 'non-positive';
        }

        return $label;
    }
}`

	fixed := redundantElse.fix(t, source)

	// The genuine either/or survives untouched…
	if !strings.Contains(fixed, `if ($n > 0) {
            $label = 'positive';
        } else {
            $label = 'non-positive';
        }`) {
		t.Fatalf("%s", fixed)
	}
	// …while the redundant one is unwrapped.
	if !strings.Contains(fixed, `            return 'left';
        }

        return 'right';`) {
		t.Fatalf("%s", fixed)
	}
	parses(t, fixed)
}
