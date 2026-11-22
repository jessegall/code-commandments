package samples

import (
	"bytes"
	"fmt"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/contract/marker"
)

// Status is how one marker came out against the bridge's tree.
type Status string

const (
	Passed  Status = "passed"
	Wrong   Status = "wrong"
	Invalid Status = "invalid"
)

// Occurrence is one marker checked against the tree a bridge wrote: what it expected, what was there, and how it
// came out.
type Occurrence struct {
	Marker      marker.Marker
	Language    contract.Language
	Case        string
	Status      Status
	Expected    string
	Actual      string
	Reason      string
	AbsenceOnly bool
}

func (o Occurrence) String() string {
	text := fmt.Sprintf("%s:%d: %s %s", o.Marker.Path, o.Marker.Line, o.Marker.Key, o.Status)
	if o.Reason != "" {
		text += ": " + o.Reason
	}
	if o.Expected != "" || o.Actual != "" {
		text += fmt.Sprintf(" (expected %s, written %s)", o.Expected, o.Actual)
	}

	return text
}

func OwnerOf(each marker.Marker) (string, error) {
	parts := strings.Split(each.Key, "/")
	switch {
	case each.Owner != "":
		return each.Owner, nil
	case len(parts) >= 2 && parts[0] != "promise" && parts[1] != "*":
		return parts[1], nil
	}

	return "", fmt.Errorf("%s names no node kind: write on KIND", each.Key)
}

func Select(file *contract.File, source []byte, each marker.Marker) (*contract.Node, error) {
	owner, err := OwnerOf(each)
	if err != nil {
		return nil, err
	}

	return NodeAt(file, source, each.Line, owner, each.Column)
}

func NodeAt(file *contract.File, source []byte, line int, kind string, column int) (*contract.Node, error) {
	var candidates []*contract.Node
	for _, node := range file.Nodes() {
		if node.Kind == kind && node.Span.Line == line && (column == 0 || ColumnOf(source, node.Span.Start) == column) {
			candidates = append(candidates, node)
		}
	}
	if len(candidates) == 1 {
		return candidates[0], nil
	}
	columns := make([]string, len(candidates))
	for index, candidate := range candidates {
		columns[index] = fmt.Sprint(ColumnOf(source, candidate.Span.Start))
	}
	if len(candidates) == 0 {
		return nil, fmt.Errorf("no %s starts on line %d", kind, line)
	}

	return nil, fmt.Errorf("%d nodes of kind %s start on line %d, at columns %s: write at COLUMN", len(candidates), kind, line, strings.Join(columns, ", "))
}

func ColumnOf(source []byte, offset int) int {
	return offset - (bytes.LastIndexByte(source[:min(offset, len(source))], '\n') + 1) + 1
}
