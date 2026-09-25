package backend

import (
	"strings"
	"testing"

	rules "github.com/jessegall/code-commandments/detectors/backend"
)

var shortCircuitStatement = scribeCase{rules.ShortCircuitStatementDetector{}, ShortCircuitStatementScribe{}}

func TestShortCircuitStatementUnfoldsAnAndIntoAnIf(t *testing.T) {
	source := `<?php

class Builder
{
    public function rebuild(Node $node): void
    {
        $node->isBuilt() && $node->built()->forgetComposition();
    }
}`

	fixed := shortCircuitStatement.fixStable(t, source)

	if !strings.Contains(fixed, `        if ($node->isBuilt()) {
            $node->built()->forgetComposition();
        }`) {
		t.Fatalf("%s", fixed)
	}
}

func TestShortCircuitStatementFlipsAnOrBecauseItRunsWhenTheLeftDoesNotHold(t *testing.T) {
	source := `<?php

class Builder
{
    public function rebuild(Node $node): void
    {
        $node->isBuilt() || $node->build();
    }
}`

	fixed := shortCircuitStatement.fixStable(t, source)

	if !strings.Contains(fixed, `        if (! $node->isBuilt()) {
            $node->build();
        }`) {
		t.Fatalf("%s", fixed)
	}
}

func TestShortCircuitStatementKeepsAWholeChainAsTheCondition(t *testing.T) {
	source := `<?php

class Watch
{
    public function tick(int $idle, bool $unlocked): void
    {
        $unlocked && $idle > 300 && $this->sleep();
    }
}`

	fixed := shortCircuitStatement.fixStable(t, source)

	if !strings.Contains(fixed, `        if ($unlocked && $idle > 300) {
            $this->sleep();
        }`) {
		t.Fatalf("%s", fixed)
	}
}

func TestShortCircuitStatementFlipsAnEqualityAtTheOperator(t *testing.T) {
	// An equality has an exact inverse, so the flipped condition reads as a human writes it.
	source := `<?php

class Totals
{
    public function add(array $entry): void
    {
        $entry['cents'] === 0 || $this->record($entry);
    }
}`

	fixed := shortCircuitStatement.fixStable(t, source)

	if !strings.Contains(fixed, `if ($entry['cents'] !== 0) {`) {
		t.Fatalf("%s", fixed)
	}
}

func TestShortCircuitStatementOpensTheBlockInTheFilesOwnBraceStyle(t *testing.T) {
	source := `<?php

class Sockets
{
    public function sort(array $sockets): void
    {
        foreach ($sockets as $socket)
        {
            $socket->isInput() && $this->keep($socket);
        }
    }
}`

	fixed := shortCircuitStatement.fixStable(t, source)

	if !strings.Contains(fixed, `            if ($socket->isInput())
            {
                $this->keep($socket);
            }`) {
		t.Fatalf("%s", fixed)
	}
}

func TestShortCircuitStatementShiftsAMultiLineRightSideWithTheBlockItMovesInto(t *testing.T) {
	source := `<?php

class Dispatch
{
    public function to(object $renderable): void
    {
        $renderable instanceof Interactive || throw TargetCannotReceiveSignal::for(
            $this->target,
            $renderable::class,
        );
    }
}`

	fixed := shortCircuitStatement.fixStable(t, source)

	// Every continuation line moves the same step the `throw` itself does — the arguments do
	// not stay behind at the old indent.
	if !strings.Contains(fixed, `        if (! ($renderable instanceof Interactive)) {
            throw TargetCannotReceiveSignal::for(
                $this->target,
                $renderable::class,
            );
        }`) {
		t.Fatalf("%s", fixed)
	}
}

func TestShortCircuitStatementLeavesAThrowThatFeedsAValue(t *testing.T) {
	source := `<?php

class Dispatch
{
    public function region(array $config): string
    {
        return $config['region'] ?? throw RegionMissing::of();
    }
}`

	if shortCircuitStatement.rewrote(t, source) {
		t.Fatal("expected false")
	}
}

func TestShortCircuitStatementLeavesAShortCircuitWhoseResultIsRead(t *testing.T) {
	source := `<?php

class Builder
{
    public function ready(Node $node): bool
    {
        return $node->isBuilt() && $node->isFresh();
    }
}`

	if shortCircuitStatement.rewrote(t, source) {
		t.Fatal("expected false")
	}
}
