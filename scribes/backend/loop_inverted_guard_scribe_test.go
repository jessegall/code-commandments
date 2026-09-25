package backend

import (
	"strings"
	"testing"

	rules "github.com/jessegall/code-commandments/detectors/backend"
)

var loopInvertedGuard = scribeCase{rules.LoopInvertedGuardDetector{}, LoopInvertedGuardScribe{}}

func TestLoopInvertedGuardInvertsTheSoleBodyIfIntoAContinueGuard(t *testing.T) {
	source := `<?php

class Processor
{
    public function run(array $rows): void
    {
        foreach ($rows as $row) {
            if ($row->valid()) {
                $this->store($row);
                $this->log($row);
            }
        }
    }
}`

	fixed := loopInvertedGuard.fixStable(t, source)

	// Inverted condition + continue guard (at the loop-body level, indent 12)…
	if !strings.Contains(fixed, `if (! $row->valid()) {
                continue;
            }`) {
		t.Fatalf("%s", fixed)
	}
	// …body hoisted to the loop level (dedented one step), order preserved.
	if !strings.Contains(fixed, `            $this->store($row);
            $this->log($row);`) {
		t.Fatalf("%s", fixed)
	}
}

func TestLoopInvertedGuardFlipsAnEqualityAtTheOperator(t *testing.T) {
	// An equality has an exact inverse, so it takes one — not a `!` and a pair of parentheses.
	source := `<?php

class Processor
{
    public function run(array $rows): void
    {
        foreach ($rows as $row) {
            if ($row->state === 'ready') {
                $this->store($row);
                $this->log($row);
            }
        }
    }
}`

	if !strings.Contains(loopInvertedGuard.fixStable(t, source), `if ($row->state !== 'ready') {`) {
		t.Fatalf("%s", loopInvertedGuard.fixStable(t, source))
	}
}

func TestLoopInvertedGuardKeepsTheParenthesesARelationalComparisonActuallyNeeds(t *testing.T) {
	// `<` and `>=` are BOTH false for a NAN operand, so they are not inverses: this one keeps
	// the faithful `!`, which `!` binding tighter than `<` means it cannot lose its parentheses.
	source := `<?php

class Processor
{
    public function run(array $rows): void
    {
        foreach ($rows as $row) {
            if ($row->weight < 250.0) {
                $this->store($row);
                $this->log($row);
            }
        }
    }
}`

	if !strings.Contains(loopInvertedGuard.fixStable(t, source), `if (! ($row->weight < 250.0)) {`) {
		t.Fatalf("%s", loopInvertedGuard.fixStable(t, source))
	}
}

func TestLoopInvertedGuardWritesTheGuardInTheFilesOwnBraceStyle(t *testing.T) {
	// #416: the fix arrived K&R in an Allman codebase and had to be reformatted by hand.
	source := `<?php

class Processor
{
    public function run(array $rows): void
    {
        foreach ($rows as $row)
        {
            if ($row->valid())
            {
                $this->store($row);
                $this->log($row);
            }
        }
    }
}`

	want := `<?php

class Processor
{
    public function run(array $rows): void
    {
        foreach ($rows as $row)
        {
            if (! $row->valid())
            {
                continue;
            }

            $this->store($row);
            $this->log($row);
        }
    }
}`

	if got := loopInvertedGuard.fixStable(t, source); got != want {
		t.Fatalf("%s", got)
	}
}

func TestLoopInvertedGuardDoesNotOvershootASingleStatementFilterOrAPlainIf(t *testing.T) {
	// First loop: a ONE-statement body if (a filter-collect) — not flagged.
	// Second: a plain method-level if (not in a loop) — not flagged.
	source := `<?php

class Keeper
{
    public function collect(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            if ($row->keep()) {
                $out[] = $row;
            }
        }

        return $out;
    }

    public function label(bool $on): string
    {
        if ($on) {
            $a = 'x';

            return $a;
        }

        return 'off';
    }
}`

	if found := loopInvertedGuard.findings(t, source); len(found) != 0 {
		t.Fatalf("%s", "the sin still fires")
	}
	if got := loopInvertedGuard.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}
