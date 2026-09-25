package backend

import (
	"strings"
	"testing"

	rules "github.com/jessegall/code-commandments/detectors/backend"
)

var stackedDocblock = scribeCase{rules.StackedDocblockDetector{}, StackedDocblockScribe{}}

func TestStackedDocblockMergesTwoOneLinersIntoOneBlock(t *testing.T) {
	source := `<?php

class Corner
{
    /** Pins an element to a corner of the canvas; same-corner elements stack. */
    /** Pins a control into a corner. Nothing to pin pins nothing. */
    public function pin(): void
    {
    }
}`

	want := `<?php

class Corner
{
    /**
     * Pins an element to a corner of the canvas; same-corner elements stack.
     *
     * Pins a control into a corner. Nothing to pin pins nothing.
     */
    public function pin(): void
    {
    }
}`

	if got := stackedDocblock.fixStable(t, source); got != want {
		t.Fatalf("%s", got)
	}
}

func TestStackedDocblockMergesADescriptionWithAnAnnotationBlock(t *testing.T) {
	source := `<?php

class Roster
{
    /** The names, in arrival order. */
    /** @var list<string> */
    private array $names = [];
}`

	want := `<?php

class Roster
{
    /**
     * The names, in arrival order.
     *
     * @var list<string>
     */
    private array $names = [];
}`

	if got := stackedDocblock.fixStable(t, source); got != want {
		t.Fatalf("%s", got)
	}
}

func TestStackedDocblockLeavesASingleDocblockUntouched(t *testing.T) {
	source := `<?php

class Corner
{
    /**
     * Pins an element to a corner.
     */
    public function pin(): void
    {
    }
}`

	if stackedDocblock.rewrote(t, source) {
		t.Fatal("expected false")
	}
	if got := stackedDocblock.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}

func TestStackedDocblockDeclinesAStackWhoseTagsWouldContradictEachOther(t *testing.T) {
	// #417: PHP shows only the LAST block, so the shadowed `@return` was inert — merging PROMOTES it
	// beside a return type it contradicts, turning dead documentation into live lies.
	source := `<?php

class WizardState
{
    /**
     * @return Option<string>
     */
    /**
     * @return array<class-string, array<string, mixed>>
     */
    public function drivers(): array
    {
        return [];
    }
}`

	if stackedDocblock.rewrote(t, source) {
		t.Fatal(`a human decides which @return survives`)
	}
	if got := stackedDocblock.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}

func TestStackedDocblockDeclinesABlockStandingApartFromTheStack(t *testing.T) {
	// #415: an insertion orphaned the block belonging to a method further down — folding it in
	// attributed that method's prose to the one it happens to sit above.
	source := `<?php

class WorkerSupervisor
{
    /**
     * Stale workers are dropped so the next request rebuilds them.
     */

    /**
     * Changed source means the compiled maps describe code that no longer exists.
     */
    private function clearCompiledCaches(): void
    {
    }
}`

	if stackedDocblock.rewrote(t, source) {
		t.Fatal(`whose words those are is not a machine question`)
	}
	if got := stackedDocblock.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}

func TestStackedDocblockDeclinesAStackDocumentingAParameterTheMethodDoesNotTake(t *testing.T) {
	// #515: a method was inserted between a docblock and the method it described, leaving the two
	// blocks ADJACENT — so the apart-from-the-stack guard never saw it. Folding left the wrong
	// method carrying `@param string $reason` for a parameter it has never had, and the method
	// that owns those words with no docblock at all.
	source := `<?php

class MigrationRunStep
{
    /**
     * Record why the step stopped.
     *
     * @param  string  $reason
     */
    /**
     * Stop the step because the run it belongs to was given up on.
     */
    public function markCancelled(): void
    {
    }

    public function markFailed(string $reason): void
    {
    }
}`

	if stackedDocblock.rewrote(t, source) {
		t.Fatal(`a fold that documents a parameter the method lacks invents documentation`)
	}
	if got := stackedDocblock.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}

func TestStackedDocblockStillFoldsTagsThatSpeakAboutDifferentThings(t *testing.T) {
	source := `<?php

class Roster
{
    /**
     * @param  string  $name
     */
    /**
     * @param  int  $age
     */
    public function join(string $name, int $age): void
    {
    }
}`

	want := `<?php

class Roster
{
    /**
     * @param  string  $name
     *
     * @param  int  $age
     */
    public function join(string $name, int $age): void
    {
    }
}`

	if got := stackedDocblock.fixStable(t, source); got != want {
		t.Fatalf("%s\n%s", `two @param of DIFFERENT names do not clash`, got)
	}
}

func TestStackedDocblockIsIdempotentAndKeepsEveryWord(t *testing.T) {
	source := `<?php

/** The first description. */
/** The second one, which is the only one PHP hands to a reader. */
final class Canvas
{
}`

	fixed := stackedDocblock.fixStable(t, source)

	if !strings.Contains(fixed, `The first description.`) {
		t.Fatalf("%s", fixed)
	}
	if !strings.Contains(fixed, `The second one, which is the only one PHP hands to a reader.`) {
		t.Fatalf("%s", fixed)
	}
	if found := stackedDocblock.findings(t, fixed); len(found) != 0 {
		t.Fatal(`the sin no longer fires`)
	}
	if got := stackedDocblock.fix(t, fixed); got != fixed {
		t.Fatalf("%s\n%s", `a second pass changes nothing`, got)
	}
}
