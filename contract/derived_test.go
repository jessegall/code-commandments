package contract

import (
	"os"
	"testing"
)

func TestTheReaderLinksEachJumpToWhatItLeaves(t *testing.T) {
	for _, tree := range []string{
		"samples/testdata/unit/contract-php-jumps/php.jsonl",
		"samples/testdata/unit/contract-go-flow/go.jsonl",
	} {
		handle, err := os.Open(tree)
		if err != nil {
			t.Fatal(err)
		}
		stream, err := ReadAll(handle)
		handle.Close()
		if err != nil {
			t.Fatal(err)
		}
		file := stream.Files[0]
		var outer *Node
		jumps := 0
		for _, node := range file.Nodes() {
			switch {
			case node.Kind == "loop" && outer == nil:
				outer = node
			case node.Kind == "break" || node.Kind == "continue":
				jumps++
				if left := node.Linked("destination"); len(left) != 1 || left[0] != outer.ID {
					t.Errorf("%s: the %s at %d leaves %v, not the outer loop %d", tree, node.Kind, node.Span.Start, left, outer.ID)
				}
			case node.Kind == "goto":
				jumps++
				var reached *Node
				if ids := node.Linked("label"); len(ids) == 1 {
					reached, _ = file.Node(ids[0])
				}
				if reached == nil || reached.Kind != "label" {
					t.Errorf("%s: the goto at %d reaches no label", tree, node.Span.Start)
				} else if binding, bound := reached.Child("binding"); !bound || binding.Facts.Name != "done" {
					t.Errorf("%s: the goto at %d reaches a label other than done", tree, node.Span.Start)
				}
			}
		}
		if jumps < 3 {
			t.Errorf("%s holds %d jumps, the sample has three", tree, jumps)
		}
	}
}
