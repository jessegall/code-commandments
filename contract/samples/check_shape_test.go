package samples

import (
	"os"
	"testing"

	"github.com/jessegall/code-commandments/contract/marker"
)

func TestARoleOrLinkMarkerHoldsExactlyItsTargetsInOrder(t *testing.T) {
	elif := writtenSample(t, "elif", ".py", []marker.Marker{label("compared", 2, "comparison", 0), label("limit", 2, "literal", 0)})
	jumps := writtenSample(t, "contract-php-jumps", ".php", nil)
	for _, each := range []struct {
		from   Written
		key    string
		line   int
		target []marker.Target
		status Status
	}{
		{elif, "role/if/condition", 2, []marker.Target{{Label: "compared"}}, Passed},
		{elif, "role/if/condition", 2, []marker.Target{{Label: "limit"}}, Wrong},
		{elif, "role/if/condition", 2, []marker.Target{{Label: "compared"}, {Label: "limit"}}, Wrong},
		{elif, "role/comparison/operands", 2, []marker.Target{{Label: "limit"}, {Line: 2, Kind: "reference"}}, Wrong},
		{elif, "role/comparison/operands", 2, []marker.Target{{Line: 2, Kind: "reference"}, {Label: "limit"}}, Passed},
		{elif, "role/if/initializer", 2, []marker.Target{}, Passed},
		{elif, "kind/if", 4, nil, Passed},
		{elif, "kind/if", 3, nil, Wrong},
		{jumps, "link/continue/destination", 9, []marker.Target{{Line: 6, Kind: "loop"}}, Passed},
		{jumps, "link/continue/destination", 9, []marker.Target{{Line: 7, Kind: "loop"}}, Wrong},
		{jumps, "link/break/destination", 12, []marker.Target{{Line: 6, Kind: "loop"}}, Passed},
	} {
		occurrence := CheckShape(nil, each.from, marker.Marker{Directive: marker.Directive{Key: each.key, Operator: "->", Targets: each.target}, Line: each.line})
		if occurrence.Status != each.status {
			t.Errorf("%s on line %d: %s, want %s", each.key, each.line, occurrence, each.status)
		}
		if occurrence.AbsenceOnly != (each.target != nil && len(each.target) == 0) {
			t.Errorf("%s on line %d: absence only %v", each.key, each.line, occurrence.AbsenceOnly)
		}
	}
}

func writtenSample(t *testing.T, construct, extension string, markers []marker.Marker) Written {
	t.Helper()
	all, err := AllExpected("testdata/unit")
	if err != nil {
		t.Fatal(err)
	}
	for _, each := range all {
		if each.Construct == construct && len(each.Source) > len(extension) && each.Source[len(each.Source)-len(extension):] == extension {
			source, err := os.ReadFile(each.Source)
			if err != nil {
				t.Fatal(err)
			}

			return Written{Source: marker.Source{Path: construct + "/source" + extension, Markers: markers}, Bytes: source, File: each.File}
		}
	}
	t.Fatalf("no %s sample in %s", extension, construct)

	return Written{}
}

func label(name string, line int, kind string, column int) marker.Marker {
	return marker.Marker{Directive: marker.Directive{Label: name, Owner: kind, Column: column}, Line: line}
}
