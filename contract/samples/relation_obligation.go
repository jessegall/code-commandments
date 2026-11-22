package samples

import (
	"fmt"
	"path/filepath"
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/contract/marker"
)

func init() {
	Register("obligation-across-files", obligationAcrossFiles)
}

func obligationAcrossFiles(run ScenarioRun, answer ScenarioAnswer, each marker.Marker) Occurrence {
	occurrence := Occurrence{}
	if len(each.Targets) != 1 || each.Targets[0].Path == "" {
		return occurrence.failed(Invalid, "this promise needs one path#label target naming the declaration owed")
	}
	base, err := declaredSymbol(run, answer, each.Targets[0])
	if err != nil {
		return occurrence.failed(Invalid, err.Error())
	}
	occurrence.Expected = base
	overriding := filepath.Base(each.Path)
	for mask := range answer.Streams {
		file := run.Written(answer, mask, overriding)
		if file == nil {
			return occurrence.failed(Wrong, fmt.Sprintf("%s was not written under mask %v", overriding, run.Masks[mask]))
		}
		member, err := NodeAt(file, run.Bytes[overriding], each.Line, "function", each.Column)
		if err != nil {
			return occurrence.failed(Wrong, err.Error())
		}
		if member.Facts == nil || !slices.ContainsFunc(member.Obligations, func(owed contract.Obligation) bool { return owed.Symbol == base }) {
			occurrence.Actual = fmt.Sprint(obligationsOf(member))

			return occurrence.failed(Wrong, fmt.Sprintf("under mask %v the member names no obligation to %s", run.Masks[mask], base))
		}
	}
	occurrence.Status = Passed

	return occurrence
}

func obligationsOf(node *contract.Node) []contract.Obligation {
	if node.Facts == nil {
		return nil
	}

	return node.Obligations
}
