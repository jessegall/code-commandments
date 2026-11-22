package samples

import (
	"fmt"
	"path"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/contract/marker"
)

// Written is one marked source with the tree its bridge wrote for it.
type Written struct {
	Source marker.Source
	Bytes  []byte
	File   *contract.File
}

func ResolveTarget(scenario map[string]Written, from Written, target marker.Target) (*contract.Node, error) {
	if target.Line > 0 {
		return NodeAt(from.File, from.Bytes, target.Line, target.Kind, target.Column)
	}
	holder := from
	if target.Path != "" {
		other, found := scenario[path.Join(path.Dir(from.Source.Path), target.Path)]
		if !found {
			return nil, fmt.Errorf("%s#%s: the scenario holds no such source", target.Path, target.Label)
		}
		holder = other
	}
	label, found := holder.Source.Labels()[target.Label]
	if !found {
		return nil, fmt.Errorf("no label %s in %s", target.Label, holder.Source.Path)
	}
	node, err := NestedNodeAt(holder.File, holder.Bytes, label.Line, label.Owner, label.Directive.Column, label.Inner)
	if err != nil {
		return nil, fmt.Errorf("label %s: %v", target.Label, err)
	}

	return node, nil
}
