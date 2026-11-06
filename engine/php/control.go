package php

import "github.com/jessegall/code-commandments/engine"

var controlKinds = map[string]bool{"Stmt_If": true, "Stmt_Foreach": true, "Stmt_For": true, "Stmt_While": true}

func (n Node) ControlBlockOpener(indent string) string {
	sample, found := n.controlSample()
	if !found {
		return " {"
	}

	return engine.Source(sample.Source).BlockOpener(sample.Start, indent)
}

func (n Node) ControlBracesOnOwnLine() bool {
	sample, found := n.controlSample()

	return found && engine.Source(sample.Source).BraceOnItsOwnLine(sample.Start)
}

func (n Node) controlSample() (engine.Span, bool) {
	file := n.Source()
	if file == nil {
		return engine.Span{}, false
	}
	for _, node := range file.File.Nodes() {
		if controlKinds[node.Kind] {
			span, err := file.Match(node.ID).Span()

			return span, err == nil
		}
	}

	return engine.Span{}, false
}
