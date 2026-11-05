package engine_test

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

// TestASourceThatCannotBeTheParsesIsRefused holds the one thing a byte offset depends on: the bytes it indexes.
// A parse is held in memory and the file it was made from is read from disk when a rule asks for its text, and
// between the two the file can shrink — a worker's own next edit, a checkout, a rebase, a formatter. Every offset
// in that parse then points past the end of what was read.
//
// Reported from a consumer on v7.15.3: DuplicateFunctionDetector and NearDuplicateFunctionDetector both died with
// "slice bounds out of range [:1111] with capacity 643" on a worker's scoped judging and were skipped, so the run
// reported no duplicates at all and nothing said the rule had not been asked.
//
// So the parse says how far it reaches, and a source too short to be the one it was made from is no source: the
// read fails, every reader of it already answers for a read that failed, and nothing slices bytes that are not
// the ones the offsets mean.
func TestASourceThatCannotBeTheParsesIsRefused(t *testing.T) {
	const path = "/p/order.py"
	parsed := "class Order:\n    def total(self):\n        return 0\n"

	// The file as the parse was made from it: every offset of that parse lies inside these bytes.
	whole, err := engine.FromString(streamOf(path, parsed), map[string]string{path: parsed})
	if err != nil {
		t.Fatal(err)
	}

	if source, err := whole.Files()[0].Source(); err != nil || string(source) != parsed {
		t.Fatalf("the source the parse was made from was refused: %v", err)
	}

	// And the same parse against a file that has since shrunk. Nothing about these bytes can answer for an offset
	// the parse holds, and the span of its own root already runs past their end.
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

	// And the whole point: a rule asking for that node's span or its text gets an answer rather than taking the
	// process down with it.
	if _, err := file.Match(0).Span(); err == nil {
		t.Error("a span was built over bytes that cannot hold it")
	}

	if written := file.Match(0).Written(); written != "" {
		t.Errorf("a node's text read as %q from bytes that are not its own", written)
	}
}
