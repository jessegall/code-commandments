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
