package samples

import (
	"encoding/json"
	"fmt"
	"path/filepath"
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/contract/marker"
)

func init() {
	Register("read-declaration-resolves", readDeclarationResolves)
}

func readDeclarationResolves(run ScenarioRun, answer ScenarioAnswer, each marker.Marker) Occurrence {
	occurrence := Occurrence{}
	if len(run.Masks) != 2 || len(each.Targets) != 1 || each.Targets[0].Path == "" {
		return occurrence.failed(Invalid, "this promise needs two masks (the reading file alone, then both) and one path#label target")
	}
	reading := filepath.Base(each.Path)
	for _, file := range answer.Streams[0] {
		if !slices.Contains(run.Masks[0], filepath.Base(file.Path)) {
			return occurrence.failed(Wrong, fmt.Sprintf("asked to write %v, the bridge also wrote %s", run.Masks[0], filepath.Base(file.Path)))
		}
	}
	symbol, err := declaredSymbol(run, answer, each.Targets[0])
	if err != nil {
		return occurrence.failed(Invalid, err.Error())
	}
	alone, both := run.Written(answer, 0, reading), run.Written(answer, 1, reading)
	if alone == nil || both == nil {
		return occurrence.failed(Wrong, reading+" was not written under both masks")
	}
	occurrence.Expected = symbol
	if !resolvesOnLine(alone, each.Line, symbol) {
		return occurrence.failed(Wrong, fmt.Sprintf("written alone, nothing on line %d refers to %s", each.Line, symbol))
	}
	if shownNodes(alone) != shownNodes(both) {
		return occurrence.failed(Wrong, reading+" is written one way when the declaring file is only read and another when both are written")
	}
	occurrence.Status = Passed

	return occurrence
}

func declaredSymbol(run ScenarioRun, answer ScenarioAnswer, target marker.Target) (string, error) {
	source, found := run.Sources[target.Path]
	if !found {
		return "", fmt.Errorf("the scenario holds no %s", target.Path)
	}
	label, found := source.Labels()[target.Label]
	if !found {
		return "", fmt.Errorf("%s defines no label %s", target.Path, target.Label)
	}
	file := run.Written(answer, len(answer.Streams)-1, target.Path)
	if file == nil {
		return "", fmt.Errorf("%s was not written when every file was", target.Path)
	}
	node, err := NodeAt(file, run.Bytes[target.Path], label.Line, label.Owner, label.Directive.Column)
	if err != nil {
		return "", fmt.Errorf("label %s: %v", target.Label, err)
	}
	for _, held := range append([]*contract.Node{node}, descendants(node)...) {
		if held.Facts != nil && held.Symbol != "" {
			return held.Symbol, nil
		}
	}

	return "", fmt.Errorf("the %s labelled %s carries no symbol", label.Owner, target.Label)
}

func descendants(node *contract.Node) []*contract.Node {
	var found []*contract.Node
	for _, child := range node.Children() {
		found = append(append(found, child), descendants(child)...)
	}

	return found
}

func resolvesOnLine(file *contract.File, line int, symbol string) bool {
	return slices.ContainsFunc(file.Nodes(), func(node *contract.Node) bool {
		return node.Span.Line == line && node.Facts != nil && (node.Refers == symbol || node.Target != nil && node.Target.Symbol == symbol)
	})
}

func shownNodes(file *contract.File) string {
	shown, err := json.Marshal(file.Nodes())
	if err != nil {
		return err.Error()
	}

	return string(shown)
}
