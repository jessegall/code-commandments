package php

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// TypeReferences is, for every class the code names, the namespaces it is named in: a type hint, an `implements`, a `::class`, a `new`.
type TypeReferences struct {
	namespaces map[string]map[string]bool
}

var typeReferences = engine.Memoised(readTypeReferences)

func TypeReferencesOf(codebase *engine.Codebase) *TypeReferences {
	return typeReferences.Of(codebase)
}

func (r *TypeReferences) NamespacesNaming(class string) []string {
	var namespaces []string
	for namespace := range r.namespaces[strings.TrimLeft(class, `\`)] {
		namespaces = append(namespaces, namespace)
	}

	return namespaces
}

func readTypeReferences(codebase *engine.Codebase) *TypeReferences {
	read := &TypeReferences{namespaces: map[string]map[string]bool{}}
	for _, reference := range In(codebase).Where(engine.As(Node.IsClassReference)).Get() {
		class := strings.TrimLeft(reference.Name(), `\`)
		if read.namespaces[class] == nil {
			read.namespaces[class] = map[string]bool{}
		}
		read.namespaces[class][Node{Match: reference}.NamespaceName()] = true
	}

	return read
}

func ModuleOf(name string) string {
	segments := strings.SplitN(strings.TrimLeft(name, `\`), `\`, 3)
	if len(segments) < 2 {
		return segments[0]
	}

	return segments[0] + `\` + segments[1]
}
