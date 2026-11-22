package samples

import (
	"os"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract/marker"
)

func TestATargetResolvesToTheNodeItsLabelOrLineSitsOn(t *testing.T) {
	all, err := AllExpected("testdata/unit")
	if err != nil {
		t.Fatal(err)
	}
	var elif Expected
	for _, each := range all {
		if each.Construct == "elif" && strings.HasSuffix(each.Source, ".py") {
			elif = each
		}
	}
	source, err := os.ReadFile(elif.Source)
	if err != nil {
		t.Fatal(err)
	}
	label := func(name string, line int, kind string, column int) marker.Marker {
		return marker.Marker{Directive: marker.Directive{Label: name, Owner: kind, Column: column}, Line: line}
	}
	labels := []marker.Marker{label("inner", 4, "if", 0), label("returned", 1, "type-expression", 21), label("either", 1, "type-expression", 0)}
	here := Written{Source: marker.Source{Path: "case/python.py", Markers: labels}, Bytes: source, File: elif.File}
	there := Written{Source: marker.Source{Path: "case/other.py", Markers: labels}, Bytes: source, File: elif.File}
	scenario := map[string]Written{"case/python.py": here, "case/other.py": there}
	for written, want := range map[marker.Target][2]int{
		{Label: "inner"}:                   {4, 5},
		{Label: "returned"}:                {1, 21},
		{Line: 4, Kind: "if"}:              {4, 5},
		{Path: "other.py", Label: "inner"}: {4, 5},
	} {
		node, err := ResolveTarget(scenario, here, written)
		if err != nil {
			t.Errorf("%+v: %v", written, err)

			continue
		}
		if got := [2]int{node.Span.Line, ColumnOf(source, node.Span.Start)}; got != want {
			t.Errorf("%+v resolved to line and column %v, want %v", written, got, want)
		}
	}
	if _, err := ResolveTarget(scenario, here, marker.Target{Label: "either"}); err == nil || !strings.Contains(err.Error(), "at columns 13, 21") {
		t.Errorf("an ambiguous label resolved: %v", err)
	}
}
