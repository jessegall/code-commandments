package engine

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
)

type sourceOrigin uint8

const (
	projectSource sourceOrigin = 1 << iota
	librarySource
	unknownSource
)

type librarySources struct {
	types      map[string]sourceOrigin
	namespaces map[string]sourceOrigin
}

func ProjectMatches(matches []Match) []Match {
	if !slices.ContainsFunc(matches, Match.IsInDependency) {
		return matches
	}
	return slices.DeleteFunc(slices.Clone(matches), Match.IsInDependency)
}

func (c *Codebase) IsLibraryType(language contract.Language, name string) bool {
	return c.librarySources(language).types[qualifiedNameKey(language, name)] == librarySource
}

func (c *Codebase) IsLibraryNamespace(language contract.Language, name string) bool {
	return c.librarySources(language).namespaces[qualifiedNameKey(language, name)] == librarySource
}

func qualifiedNameKey(language contract.Language, name string) string {
	if key := grammarOf(language).QualifiedNameKey; key != nil {
		return key(name)
	}
	return name
}

type libraryLanguage struct{ language contract.Language }

func (c *Codebase) librarySources(language contract.Language) librarySources {
	return Analysis(c, libraryLanguage{language}, func(c *Codebase) librarySources {
		sources := librarySources{types: map[string]sourceOrigin{}, namespaces: map[string]sourceOrigin{}}
		read := grammarOf(language)
		add := func(name, namespace string, origin sourceOrigin) {
			sources.types[qualifiedNameKey(language, name)] |= origin
			for namespace != "" {
				sources.namespaces[qualifiedNameKey(language, namespace)] |= origin
				if read.NamespaceSeparator == "" {
					break
				}
				namespace = Before(namespace, read.NamespaceSeparator)
			}
		}
		for _, file := range c.files {
			if file.Language() != language {
				continue
			}
			for _, node := range file.Nodes() {
				if node.Kind != "type" && node.Kind != "alias" || node.Symbol == "" {
					continue
				}
				match := Match{node: node, file: file}
				origin := projectSource
				if match.IsInDependency() {
					origin = librarySource
				}
				add(node.Symbol, match.Namespace(), origin)
			}
		}
		for _, symbol := range outsideSymbols(c, language) {
			namespace := ""
			if read.NamespaceOf != nil {
				namespace = read.NamespaceOf(symbol.Symbol)
			}
			add(symbol.Symbol, namespace, outsideOrigin(symbol))
		}

		return sources
	})
}

func outsideOrigin(symbol contract.OutsideSymbol) sourceOrigin {
	if symbol.Internal || IsDependencyFile(symbol.File) {
		return librarySource
	}
	if symbol.File != "" {
		return projectSource
	}
	return unknownSource
}
