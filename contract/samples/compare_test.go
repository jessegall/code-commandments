package samples

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract"
)

func TestTheComparatorFindsEveryKindOfDifference(t *testing.T) {
	all, err := AllExpected("testdata/expected")
	if err != nil {
		t.Fatal(err)
	}
	var tree *contract.File
	for _, expected := range all {
		if expected.Construct == "reversed-error-check" {
			tree = expected.File
		}
	}
	if tree == nil {
		t.Fatal("no reversed-error-check tree")
	}
	if differences := Differences(tree, tree); len(differences) != 0 {
		t.Fatalf("a tree differs from itself: %v", differences)
	}
	changed := tree.CopiedTo(tree.Path, func(symbol string) string { return symbol })
	for _, node := range changed.Nodes() {
		if node.Kind == "comparison" {
			facts := *node.Facts
			facts.Operator = "equal"
			node.Facts = &facts
			node.Span.End++
		}
	}
	differences := strings.Join(Differences(tree, changed), "\n")
	for _, said := range []string{"fact operator not-equal expected, equal written", "span"} {
		if !strings.Contains(differences, said) {
			t.Errorf("the comparator did not say %q: %s", said, differences)
		}
	}
}
