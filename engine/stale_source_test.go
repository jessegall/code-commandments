package engine_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestASourceThatCannotBeTheParsesIsRefused(t *testing.T) {
	const path = "/p/order.py"
	parsed := "class Order:\n    def total(self):\n        return 0\n"

	whole, err := engine.FromString(streamOf(path, parsed), map[string]string{path: parsed})
	if err != nil {
		t.Fatal(err)
	}

	if source, err := whole.Files()[0].Source(); err != nil || string(source) != parsed {
		t.Fatalf("the source the parse was made from was refused: %v", err)
	}

	shrunk := "class Order:\n"
	cut, err := engine.FromString(streamOf(path, parsed), map[string]string{path: shrunk})
	if err != nil {
		t.Fatal(err)
	}

	file := cut.Files()[0]

	source, err := file.Source()
	if err == nil {
		t.Fatalf("a source of %d bytes answered for a parse that reaches %d", len(source), len(parsed))
	}

	if !strings.Contains(err.Error(), path) {
		t.Errorf("the refusal does not name the file: %v", err)
	}

	if _, err := file.Match(0).Span(); err == nil {
		t.Error("a span was built over bytes that cannot hold it")
	}

	if written := file.Match(0).Written(); written != "" {
		t.Errorf("a node's text read as %q from bytes that are not its own", written)
	}
}
