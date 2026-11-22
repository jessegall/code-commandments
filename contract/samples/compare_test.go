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
			node.Own().Operator = "equal"
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

func TestTheComparisonLeavesOutMarkerCommentsButKeepsOrdinaryOnes(t *testing.T) {
	source := []byte("<?php\n// ordinary\n$a = 1; // @contract kind/literal\n")
	ordinary := contract.Comment{Form: "line", Text: "ordinary", Span: contract.Span{Start: 6, End: 17, Line: 2}}
	marked := contract.Comment{Form: "line", Text: "@contract kind/literal", Span: contract.Span{Start: 26, End: 51, Line: 3}, Trailing: true}
	expected := &contract.File{Comments: []contract.Comment{ordinary}}
	if differences := Differences(expected, WithoutMarkers(contract.PHP, source, &contract.File{Comments: []contract.Comment{ordinary, marked}})); len(differences) > 0 {
		t.Errorf("a marker comment was compared: %v", differences)
	}
	if differences := Differences(expected, WithoutMarkers(contract.PHP, source, &contract.File{Comments: []contract.Comment{marked}})); len(differences) == 0 {
		t.Error("a missing ordinary comment went unnoticed")
	}
}
