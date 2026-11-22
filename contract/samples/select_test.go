package samples

import (
	"os"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/contract/marker"
)

func TestAMarkerSelectsTheOneNodeOfItsKindThatStartsOnItsLine(t *testing.T) {
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
	at := func(line int, key string, column int) (int, error) {
		node, err := Select(elif.File, source, marker.Marker{Directive: marker.Directive{Key: key, Column: column}, Line: line})
		if err != nil {
			return 0, err
		}

		return ColumnOf(source, node.Span.Start), nil
	}
	if column, err := at(4, "kind/if", 0); err != nil || column != 5 {
		t.Errorf("the elif on line 4: column %d, %v", column, err)
	}
	if _, err := at(3, "kind/if", 0); err == nil || !strings.Contains(err.Error(), "no if starts on line 3") {
		t.Errorf("the if spanning line 3 was selected: %v", err)
	}
	if _, err := at(1, "kind/type-expression", 0); err == nil || !strings.Contains(err.Error(), "at columns 13, 21") {
		t.Errorf("two type expressions on line 1 were not both named: %v", err)
	}
	if column, err := at(1, "kind/type-expression", 21); err != nil || column != 21 {
		t.Errorf("at 21 chose column %d, %v", column, err)
	}
	if _, err := at(1, "fact/*/constantValue", 0); err == nil || !strings.Contains(err.Error(), "write on KIND") {
		t.Errorf("a universal fact without on KIND was accepted: %v", err)
	}
}
