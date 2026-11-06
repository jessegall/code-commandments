package engine

import "strings"

// LayerStack is the layers a project declares, each with the names it may reference, read in one language's spelling: its separator, and whether its names compare by case.
type LayerStack struct {
	layers        map[string][]string
	separator     string
	caseSensitive bool
}

func Layers(layers map[string][]string, separator string, caseSensitive bool) LayerStack {
	return LayerStack{layers: layers, separator: separator, caseSensitive: caseSensitive}
}

func (s LayerStack) IsEmpty() bool {
	return len(s.layers) == 0
}

func (s LayerStack) LayerOf(name string) string {
	found := ""
	for layer := range s.layers {
		if s.within(name, layer) && len(layer) > len(found) {
			found = layer
		}
	}

	return found
}

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

func (s LayerStack) within(name, prefix string) bool {
	name, prefix = strings.Trim(name, s.separator), strings.Trim(prefix, s.separator)
	if !s.caseSensitive {
		name, prefix = strings.ToLower(name), strings.ToLower(prefix)
	}

	return prefix == "" || name == prefix || strings.HasPrefix(name, prefix+s.separator)
}

func (s LayerStack) InLayer(name, layer string) bool {
	found := s.LayerOf(name)

	return found != "" && s.within(found, layer) && s.within(layer, found)
}
