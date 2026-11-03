package golang_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/golang"
)

// Every other language registers its fingerprint, so `repeated` and `duplicated` answered for five languages and
// silently answered nothing for Go. These hold Go's: two bodies that differ only in what a local is called are
// one body, two that differ in which datum they hold are one body normalised and two as written, and two that do
// different things are two either way.

// TestTwoBodiesThatDifferOnlyInANameAreOneNormalised holds both readings apart: the body hash keeps the names, as
// `duplicated` reads a body, and the normalised node hash blanks them, as `repeated` reads a node.
func TestTwoBodiesThatDifferOnlyInANameAreOneNormalised(t *testing.T) {
	codebase := codebaseOf(t, map[string]string{
		"go.mod": "module example.com/shop\n",
		"till/till.go": `package till

func Settled(lines []int) int {
	total := 0
	for _, line := range lines {
		total += line
	}

	return total
}

func Counted(rows []int) int {
	sum := 0
	for _, row := range rows {
		sum += row
	}

	return sum
}

func Highest(lines []int) int {
	most := 0
	for _, line := range lines {
		if line > most {
			most = line
		}
	}

	return most
}
`,
	})

	bodies, functions := map[string]string{}, map[string]engine.Match{}
	for _, function := range golang.In(codebase).WhereFunction().Get() {
		bodies[function.Name()] = function.BodyHash()
		functions[function.Name()] = function
	}

	if bodies["Settled"] == "" {
		t.Fatal("a Go function has no body hash, so every repeat and duplicate question answers nothing for Go")
	}

	// As written, a local's name is part of the body: `duplicated` says two functions HAVE one body, and these
	// two do not.
	if bodies["Settled"] == bodies["Counted"] {
		t.Error("two bodies whose locals are named differently read as one body as written")
	}

	// Normalised, the names are blanked, which is what a rule asking for a repeat reads a node by.
	if golang.NodeHash(functions["Settled"], true) != golang.NodeHash(functions["Counted"], true) {
		t.Error("two functions differing only in their locals' names read as two normalised")
	}

	if golang.NodeHash(functions["Settled"], true) == golang.NodeHash(functions["Highest"], true) {
		t.Error("a function that sums and one that picks the highest read as one normalised")
	}
}

// TestADatumIsBlankedNormalisedAndKeptAsWritten holds the other half: the normalised reading is blind to which
// datum a literal holds, and the exact reading is not, which is what a rule about a repeated string needs.
func TestADatumIsBlankedNormalisedAndKeptAsWritten(t *testing.T) {
	codebase := codebaseOf(t, map[string]string{
		"go.mod": "module example.com/shop\n",
		"till/till.go": `package till

func Paid() string {
	return "paid"
}

func Sent() string {
	return "sent"
}
`,
	})

	nodes := map[string]engine.Match{}
	for _, function := range golang.In(codebase).WhereFunction().Get() {
		nodes[function.Name()] = function
	}

	paid, sent := nodes["Paid"], nodes["Sent"]
	if !paid.Exists() || !sent.Exists() {
		t.Fatal("the two functions are not in the codebase")
	}

	if golang.NodeHash(paid, true) != golang.NodeHash(sent, true) {
		t.Error("two functions differing only in which string they return read as two normalised")
	}

	if golang.NodeHash(paid, false) == golang.NodeHash(sent, false) {
		t.Error("two functions returning different strings read as one as written, so a repeated literal cannot be told")
	}
}
