package samples

import (
	"errors"
	"reflect"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/contract/marker"
)

func TestAKeyIsProvenOnlyWhenEveryOneOfItsMarkersPasses(t *testing.T) {
	occurrence := func(key string, line int, status Status) Occurrence {
		return Occurrence{Marker: marker.Marker{Directive: marker.Directive{Key: key}, Path: "case/php.php", Line: line}, Status: status}
	}
	debt := Debt{Owed: []string{"link/break/destination"}}
	score := ScoreOf(contract.PHP, []Occurrence{
		occurrence("link/break/destination", 3, Passed),
		occurrence("link/break/destination", 4, Passed),
		occurrence("link/break/destination", 5, Wrong),
	}, debt, nil, false)
	if score.Passed != 2 || score.Total != 3 || score.Proven != 0 || score.Owed != 1 || score.Holds() {
		t.Errorf("the doc's example scored %+v", score)
	}
	var ten []Occurrence
	for line := range 10 {
		ten = append(ten, occurrence("kind/loop", line+1, Passed))
	}
	twoKeys := Debt{Owed: []string{"kind/if", "kind/loop"}}
	if score := ScoreOf(contract.PHP, ten, twoKeys, nil, false); score.Proven != 1 || !reflect.DeepEqual(score.Missing, []string{"kind/if"}) {
		t.Errorf("ten copies of one key scored %+v", score)
	}
	if score := ScoreOf(contract.PHP, nil, Debt{}, nil, false); score.Holds() || !slices.Contains(score.Faults, "nothing is owed, so nothing is proven") {
		t.Errorf("an empty denominator held: %+v", score)
	}
	if score := ScoreOf(contract.PHP, ten[:1], Debt{Owed: []string{"kind/loop"}}, []error{errors.New("a malformed marker")}, false); score.Holds() {
		t.Errorf("a marker fault did not fail the gate: %+v", score)
	}
	if score := ScoreOf(contract.PHP, ten[:1], Debt{Owed: []string{"kind/loop"}}, nil, true); score.Holds() || score.Report()[0] != "php: incomplete execution" {
		t.Errorf("an incomplete run held: %v", score.Report())
	}
	first := ScoreOf(contract.PHP, ten, twoKeys, nil, false).Report()
	if second := ScoreOf(contract.PHP, ten, twoKeys, nil, false).Report(); !reflect.DeepEqual(first, second) {
		t.Errorf("two runs reported differently:\n%v\n%v", first, second)
	}
}
