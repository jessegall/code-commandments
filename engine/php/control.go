package php

import "github.com/jessegall/code-commandments/engine"

// controlKinds are the control structures a file's brace style is read from.
var controlKinds = map[string]bool{"Stmt_If": true, "Stmt_Foreach": true, "Stmt_For": true, "Stmt_While": true}

// ControlBlockOpener is how a control block opens in the node's file, read from its first control structure: " {"
// after the header, or the brace on a line of its own at indent; " {" when the file has none.
func (n Node) ControlBlockOpener(indent string) string {
	sample, found := n.controlSample()
	if !found {
		return " {"
	}

	return engine.Source(sample.Source).BlockOpener(sample.Start, indent)
}

// ControlBracesOnOwnLine says whether the node's file stands a control structure's brace on a line of its own.
func (n Node) ControlBracesOnOwnLine() bool {
	sample, found := n.controlSample()

	return found && engine.Source(sample.Source).BraceOnItsOwnLine(sample.Start)
}

// controlSample is the span of the file's first control structure, in source order.
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
