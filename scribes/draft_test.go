package scribes

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func spanOf(path, source string, start, end int) engine.Span {
	return engine.Span{Path: path, Source: []byte(source), Start: start, End: end}
}

func TestEditsApplyRightToLeftWithOffsetsIntact(t *testing.T) {
	source := "alpha beta gamma"
	draft := NewDraft().
		Edit(spanOf("a.php", source, 0, 5), "ALPHA").
		Edit(spanOf("a.php", source, 11, 16), "G")

	if got := draft.Rewrites().Content("a.php"); got != "ALPHA beta G" {
		t.Fatalf("got %q", got)
	}
}

func TestAnInsertionComposesWithAReplacementAtTheSameStart(t *testing.T) {
	source := "public int $x;"
	draft := NewDraft().
		Edit(spanOf("a.php", source, 0, 0), "#[Hidden] ").
		Edit(spanOf("a.php", source, 0, 7), "")

	if got := draft.Rewrites().Content("a.php"); got != "#[Hidden] int $x;" {
		t.Fatalf("got %q", got)
	}
}

func TestAnEditOverlappingOneAlreadyAppliedIsSkipped(t *testing.T) {
	source := "f(g(x))"
	draft := NewDraft().
		Edit(spanOf("a.php", source, 2, 6), "y").
		Edit(spanOf("a.php", source, 0, 7), "z")

	if got := draft.Rewrites().Content("a.php"); got != "f(y)" {
		t.Fatalf("got %q", got)
	}
}

func TestIdenticalEditsAreOneEdit(t *testing.T) {
	source := "<script>"
	draft := NewDraft().
		Edit(spanOf("a.vue", source, 8, 8), "\nimport A from './A.vue'").
		Edit(spanOf("a.vue", source, 8, 8), "\nimport A from './A.vue'")

	if got := draft.Rewrites().Content("a.vue"); got != "<script>\nimport A from './A.vue'" {
		t.Fatalf("got %q", got)
	}
}

func TestANewFileNeverClobbersAnother(t *testing.T) {
	rewrites := NewDraft().Add("Card.vue", "1").Add("Card.vue", "2").Add("Card.vue", "3").Add("Makefile", "m").Add("Makefile", "n").Rewrites()

	want := []string{"Card.vue", "Card2.vue", "Card3.vue", "Makefile", "Makefile2"}
	if got := rewrites.Paths(); !slices.Equal(got, want) {
		t.Fatalf("got %v, want %v", got, want)
	}
}

func TestNewFilesComeBeforeEditedOnesAndEachFileKeepsItsFirstPlace(t *testing.T) {
	rewrites := NewDraft().
		Edit(spanOf("b.php", "b", 0, 1), "B").
		Add("New.vue", "n").
		Edit(spanOf("a.php", "a", 0, 1), "A").
		Edit(spanOf("b.php", "b", 0, 0), "_").
		Rewrites()

	want := []string{"New.vue", "b.php", "a.php"}
	if got := rewrites.Paths(); !slices.Equal(got, want) {
		t.Fatalf("got %v, want %v", got, want)
	}
	if got := rewrites.Content("b.php"); got != "_B" {
		t.Fatalf("got %q", got)
	}
}
