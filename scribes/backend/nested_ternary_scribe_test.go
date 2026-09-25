package backend

import (
	"strings"
	"testing"

	rules "github.com/jessegall/code-commandments/detectors/backend"
)

var nestedTernary = scribeCase{rules.NestedTernaryDetector{}, NestedTernaryScribe{}}

func TestNestedTernaryUnfoldsAnElseChainedTernaryIntoMatchTrue(t *testing.T) {
	source := `<?php

class Grader
{
    public function grade(int $n): string
    {
        return $n > 90 ? 'A' : ($n > 80 ? 'B' : 'C');
    }
}`

	fixed := nestedTernary.fixStable(t, source)

	want := `        return match (true) {
            $n > 90 => 'A',
            $n > 80 => 'B',
            default => 'C',
        };`

	if !strings.Contains(fixed, want) {
		t.Fatalf("%s", fixed)
	}
}

func TestNestedTernaryDoesNotOvershootASingleTernaryOrAThenNestedChain(t *testing.T) {
	// A plain single ternary is not flagged at all; a THEN-nested chain IS flagged by
	// the detector but the scribe skips it (it can't flatten to a clean match), so the
	// source is left unchanged.
	source := `<?php

class Picker
{
    public function simple(bool $a, string $b, string $c): string
    {
        return $a ? $b : $c;
    }

    public function thenNested(bool $a, bool $b): string
    {
        return $a ? ($b ? 'x' : 'y') : 'z';
    }
}`

	if got := nestedTernary.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
	if !strings.Contains(nestedTernary.fix(t, source), `return $a ? ($b ? 'x' : 'y') : 'z';`) {
		t.Fatalf("%s", nestedTernary.fix(t, source))
	}
}
