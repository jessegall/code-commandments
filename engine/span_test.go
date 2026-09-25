package engine_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestSpanLocatesItsText(t *testing.T) {
	source := []byte("<?php\n\nfunction f() {\n    return $x;\n}\n")
	span := engine.Span{Path: "f.php", Source: source, Start: 26, End: 36}

	if span.Text() != "return $x;" || span.Line() != 4 || span.Column() != 4 || span.LineIndent() != "    " {
		t.Fatalf("got %q line %d column %d indent %q", span.Text(), span.Line(), span.Column(), span.LineIndent())
	}
	inner := engine.Span{Path: "f.php", Source: source, Start: 33, End: 35}
	if inner.LineIndent() != "" {
		t.Fatalf("code precedes $x on its line, got indent %q", inner.LineIndent())
	}
	if !span.Contains(inner) || inner.Contains(span) {
		t.Fatal("the statement strictly contains its variable")
	}
	elsewhere := engine.Span{Path: "g.php", Source: source, Start: 33, End: 35}
	if span.Contains(elsewhere) {
		t.Fatal("a span in another file is never contained")
	}
}

func TestFindingMarksACustomRule(t *testing.T) {
	if (engine.Finding{Detector: "ArrayBag"}).Rule() != "ArrayBag" || (engine.Finding{Detector: "Mine", Custom: true}).Rule() != "Mine (custom)" {
		t.Fatal("a custom rule is marked, a shipped one is not")
	}
}
