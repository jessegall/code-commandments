package contract

import (
	"path/filepath"
	"slices"
	"strings"
)

// Languages is every language the tool reads, in the order the curriculum teaches them.
var Languages = []Language{PHP, Vue, TypeScript, Python, CSharp, Go}

// extensions are the file extensions each language's files carry, its own first. TypeScript's compiler reads the
// module and JSX forms, and JavaScript, as one language.
var extensions = map[Language][]string{
	PHP:        {"php"},
	Vue:        {"vue"},
	TypeScript: {"ts", "tsx", "mts", "cts", "js", "jsx", "mjs", "cjs"},
	Python:     {"py"},
	CSharp:     {"cs"},
	Go:         {"go"},
}

// ExtensionsOf is every extension this language's files carry, its own first.
func ExtensionsOf(language Language) []string {
	return extensions[language]
}

// LanguageOfPath is the language the file at path is written in, and whether any language claims its extension.
//
// It is here, below every reader, because the question is one: the engine asks it of a file it holds and the
// scan asks it of a file it walks, and two tables would answer differently the moment a language was added to
// one of them. A `.go` file read as PHP, which is what a table that had not been told would say, is a wrong
// answer nothing reports.
func LanguageOfPath(path string) (Language, bool) {
	extension := strings.ToLower(strings.TrimPrefix(filepath.Ext(path), "."))
	for _, language := range Languages {
		if slices.Contains(extensions[language], extension) {
			return language, true
		}
	}

	return "", false
}
