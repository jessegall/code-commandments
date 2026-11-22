package samples

import (
	"fmt"
	"path/filepath"

	"github.com/jessegall/code-commandments/contract/marker"
)

func init() {
	Register("read-links-binding", readLinksBinding)
}

func readLinksBinding(run ScenarioRun, answer ScenarioAnswer, each marker.Marker) Occurrence {
	occurrence := Occurrence{}
	if len(each.Targets) != 1 || each.Targets[0].Label == "" || each.Targets[0].Path != "" {
		return occurrence.failed(Invalid, "this promise needs one label of the same file naming the binding read")
	}
	name := filepath.Base(each.Path)
	file := run.Written(answer, len(answer.Streams)-1, name)
	if file == nil {
		return occurrence.failed(Wrong, name+" was not written")
	}
	label, found := run.Sources[name].Labels()[each.Targets[0].Label]
	if !found {
		return occurrence.failed(Invalid, "no label "+each.Targets[0].Label)
	}
	binding, err := NestedNodeAt(file, run.Bytes[name], label.Line, label.Owner, label.Directive.Column, label.Inner)
	if err != nil {
		return occurrence.failed(Invalid, fmt.Sprintf("label %s: %v", label.Label, err))
	}
	read, err := NodeAt(file, run.Bytes[name], each.Line, "reference", each.Column)
	if err != nil {
		return occurrence.failed(Wrong, err.Error())
	}
	linked := read.Linked("binding")
	occurrence.Expected, occurrence.Actual = fmt.Sprintf("[%d]", binding.ID), fmt.Sprint(linked)
	if len(linked) != 1 || linked[0] != binding.ID {
		return occurrence.failed(Wrong, "the read does not link to the labelled binding")
	}
	occurrence.Status = Passed

	return occurrence
}
