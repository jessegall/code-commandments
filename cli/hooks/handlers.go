package hooks

// writers are the tools whose edits name the file they wrote.
var writers = []string{"Edit", "Write", "MultiEdit"}

func bound(event string, tools []string) []Binding {
	var bindings []Binding

	for _, tool := range tools {
		bindings = append(bindings, Binding{event, tool})
	}

	return bindings
}
