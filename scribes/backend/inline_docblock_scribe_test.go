package backend

import (
	"testing"

	rules "github.com/jessegall/code-commandments/detectors/backend"
)

var inlineDocblock = scribeCase{rules.InlineDocblockDetector{}, InlineDocblockScribe{}}

func TestInlineDocblockExpandsAOneLineDocblockAtItsOwnIndentation(t *testing.T) {
	source := `<?php

class Wire
{
    /** The quick-add menu a released wire reveals — where it opens comes from the release. */
    public function menu(): void
    {
    }
}`

	want := `<?php

class Wire
{
    /**
     * The quick-add menu a released wire reveals — where it opens comes from the release.
     */
    public function menu(): void
    {
    }
}`

	if got := inlineDocblock.fixStable(t, source); got != want {
		t.Fatalf("%s", got)
	}
}

func TestInlineDocblockExpandsAnAnnotationAndAClassLevelBlock(t *testing.T) {
	source := `<?php

/** Pins an element to a corner of the canvas. */
final class Corner
{
    /** @var list<string> */
    private array $names = [];
}`

	want := `<?php

/**
 * Pins an element to a corner of the canvas.
 */
final class Corner
{
    /**
     * @var list<string>
     */
    private array $names = [];
}`

	if got := inlineDocblock.fixStable(t, source); got != want {
		t.Fatalf("%s", got)
	}
}

func TestInlineDocblockKeepsEveryContentLineAndTheBlankBetweenParagraphs(t *testing.T) {
	source := `<?php

class Wire
{
    /** Opens here
     * and runs on.
     *
     * @param int $slot The slot.
     */
    public function menu(int $slot): void
    {
    }
}`

	want := `<?php

class Wire
{
    /**
     * Opens here
     * and runs on.
     *
     * @param int $slot The slot.
     */
    public function menu(int $slot): void
    {
    }
}`

	if got := inlineDocblock.fixStable(t, source); got != want {
		t.Fatalf("%s", got)
	}
}

func TestInlineDocblockLeavesADocblockThatIsAlreadyABlock(t *testing.T) {
	source := `<?php

class Wire
{
    /**
     * Already shaped like a block.
     */
    public function menu(): void
    {
    }
}`

	if inlineDocblock.rewrote(t, source) {
		t.Fatal("expected false")
	}
	if got := inlineDocblock.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}

func TestInlineDocblockIsIdempotent(t *testing.T) {
	source := `<?php

class Wire
{
    /** One line. */
    public function menu(): void
    {
    }
}`

	fixed := inlineDocblock.fixStable(t, source)

	if found := inlineDocblock.findings(t, fixed); len(found) != 0 {
		t.Fatal(`the sin no longer fires`)
	}
	if got := inlineDocblock.fix(t, fixed); got != fixed {
		t.Fatalf("%s\n%s", `a second pass changes nothing`, got)
	}
}
