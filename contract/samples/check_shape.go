package samples

import (
	"fmt"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/contract/marker"
)

func CheckShape(scenario map[string]Written, from Written, each marker.Marker) Occurrence {
	occurrence := Occurrence{Marker: each, Language: from.File.Language}
	node, err := Select(from.File, from.Bytes, each)
	if err != nil {
		return occurrence.failed(Wrong, err.Error())
	}
	parts := strings.Split(each.Key, "/")
	if parts[0] == "kind" {
		occurrence.Status = Passed

		return occurrence
	}
	var expected []*contract.Node
	for _, target := range each.Targets {
		resolved, err := ResolveTarget(scenario, from, target)
		if err != nil {
			return occurrence.failed(Invalid, err.Error())
		}
		expected = append(expected, resolved)
	}
	var actual []*contract.Node
	switch parts[0] {
	case "role":
		actual, _ = node.Role(parts[2])
	case "link":
		for _, id := range node.Linked(parts[2]) {
			linked, found := from.File.Node(id)
			if !found {
				return occurrence.failed(Wrong, fmt.Sprintf("link %s points at node %d, which the file does not hold", parts[2], id))
			}
			actual = append(actual, linked)
		}
	default:
		return occurrence.failed(Invalid, each.Key+" is no kind, role or link")
	}
	occurrence.Expected, occurrence.Actual = described(from, expected), described(from, actual)
	occurrence.AbsenceOnly = len(expected) == 0
	if !sameNodes(expected, actual) {
		occurrence.Status = Wrong

		return occurrence
	}
	occurrence.Status = Passed

	return occurrence
}

func (o Occurrence) failed(status Status, reason string) Occurrence {
	o.Status, o.Reason = status, reason

	return o
}

func sameNodes(expected, actual []*contract.Node) bool {
	if len(expected) != len(actual) {
		return false
	}
	for index := range expected {
		if expected[index] != actual[index] {
			return false
		}
	}

	return true
}

func described(from Written, nodes []*contract.Node) string {
	written := make([]string, len(nodes))
	for index, node := range nodes {
		written[index] = fmt.Sprintf("%s at %d:%d", node.Kind, node.Span.Line, ColumnOf(from.Bytes, node.Span.Start))
	}

	return "[" + strings.Join(written, ", ") + "]"
}
