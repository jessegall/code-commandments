package engine_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestATokenBeginningItsLineIsToldFromOneTrailingCode(t *testing.T) {
	source := engine.Source("<?php\n    public string $a; // note\n")

	if source.StartsItsLine(strings.Index(string(source), "//")) {
		t.Fatal("a trailing note does not begin its line")
	}
	if !source.StartsItsLine(strings.Index(string(source), "public")) {
		t.Fatal("the declaration does")
	}
}

func TestATokenAtColumnZeroBeginsItsLine(t *testing.T) {
	source := engine.Source("<?php\n/** Doc. */\nfunction f() {}\n")
	doc := strings.Index(string(source), "/**")

	if !source.StartsItsLine(doc) {
		t.Fatal("column zero begins its line")
	}
	if indent, own := source.OwnLineIndent(doc); !own || indent != "" {
		t.Fatalf("no indentation is still its own line, got %q %v", indent, own)
	}
}

func TestLineContentEndStopsBeforeThePaddingAndTheBreak(t *testing.T) {
	source := engine.Source("<?php\n    public string $a; // note  \n    public int $b;\n")
	end := source.LineContentEndAt(strings.Index(string(source), "public"))

	if got := string(source[6:end]); got != "    public string $a; // note" {
		t.Fatalf("got %q", got)
	}
}

func TestLineContentEndOfTheFinalUnterminatedLineIsItsEnd(t *testing.T) {
	source := engine.Source("<?php\n$a = 1;")

	if got := source.LineContentEndAt(len(source) - 1); got != len(source) {
		t.Fatalf("got %d", got)
	}
}

func TestABlockOpensInTheFilesOwnBraceStyle(t *testing.T) {
	sameLine := engine.Source("if ($a) {\n}")
	ownLine := engine.Source("    if ($a)\n    {\n    }")

	if got := sameLine.BlockOpener(0, "    "); got != " {" {
		t.Fatalf("got %q", got)
	}
	if got := ownLine.BlockOpener(0, "    "); got != "\n    {" {
		t.Fatalf("got %q", got)
	}
	if engine.Source("no brace").BraceOnItsOwnLine(0) {
		t.Fatal("no brace is not a brace on its own line")
	}
}

func TestReindentShiftsContinuationLinesByTheLinesIndent(t *testing.T) {
	source := []byte("        $a = $cond\n            || throw X::for(\n                1,\n\n            );\n")
	start := strings.Index(string(source), "$cond")
	end := strings.LastIndex(string(source), ")") + 1
	span := engine.Span{Source: source, Start: start, End: end}

	want := "  $cond\n      || throw X::for(\n          1,\n\n      )"
	if got := span.Reindent("  "); got != want {
		t.Fatalf("got\n%s\nwant\n%s", got, want)
	}
}

func TestOffsetsAroundALine(t *testing.T) {
	source := engine.Source("a\n  bc  \nd")
	pos := strings.Index(string(source), "c")

	if source.LineStartAt(pos) != 2 || source.LineEndAt(pos) != 9 || source.LineAt(pos) != 2 || source.LineIndentAt(pos) != "  " {
		t.Fatalf("start %d end %d line %d indent %q", source.LineStartAt(pos), source.LineEndAt(pos), source.LineAt(pos), source.LineIndentAt(pos))
	}
	if at, found := source.Before(pos, "a"); !found || at != 0 {
		t.Fatalf("before %d %v", at, found)
	}
	if _, found := source.After(pos, "a"); found {
		t.Fatal("no a after c")
	}
	if got := source.SkipWhitespace(pos+1, len(source)); got != 9 {
		t.Fatalf("skip %d", got)
	}
}
