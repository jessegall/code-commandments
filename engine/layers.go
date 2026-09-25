package engine

import "strings"

// LayerStack is the layers a project declares, each with the names it may reference, read in one language's
// spelling: its separator, and whether its names compare by case.
type LayerStack struct {
	layers        map[string][]string
	separator     string
	caseSensitive bool
}

// Layers is the stack the declarations make.
func Layers(layers map[string][]string, separator string, caseSensitive bool) LayerStack {
	return LayerStack{layers: layers, separator: separator, caseSensitive: caseSensitive}
}

// IsEmpty says whether no layer is declared.
func (s LayerStack) IsEmpty() bool {
	return len(s.layers) == 0
}

// LayerOf is the most specific declared layer the name falls in; empty when it falls in none.
func (s LayerStack) LayerOf(name string) string {
	found := ""
	for layer := range s.layers {
		if s.within(name, layer) && len(layer) > len(found) {
			found = layer
		}
	}

	return found
}

// MayReference says whether code in the layer may reference the target: its own layer always, else one of the
// names it declared it may use.
func (s LayerStack) MayReference(layer, target string) bool {
	if s.within(target, layer) {
		return true
	}
	for _, allowed := range s.layers[layer] {
		if s.within(target, strings.Trim(allowed, s.separator)) {
			return true
		}
	}

	return false
}

// within says whether the name is the prefix itself, or nested inside it.
func (s LayerStack) within(name, prefix string) bool {
	name, prefix = strings.Trim(name, s.separator), strings.Trim(prefix, s.separator)
	if !s.caseSensitive {
		name, prefix = strings.ToLower(name), strings.ToLower(prefix)
	}

	return prefix == "" || name == prefix || strings.HasPrefix(name, prefix+s.separator)
}
