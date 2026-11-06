package contract

import (
	"path/filepath"
	"slices"
	"strings"
)

var Languages = []Language{PHP, Vue, TypeScript, Python, CSharp, Go}

var extensions = map[Language][]string{
	PHP:        {"php"},
	Vue:        {"vue"},
	TypeScript: {"ts", "tsx", "mts", "cts", "js", "jsx", "mjs", "cjs"},
	Python:     {"py"},
	CSharp:     {"cs"},
	Go:         {"go"},
}

func ExtensionsOf(language Language) []string {
	return extensions[language]
}

func LanguageOfPath(path string) (Language, bool) {
	extension := strings.ToLower(strings.TrimPrefix(filepath.Ext(path), "."))
	for _, language := range Languages {
		if slices.Contains(extensions[language], extension) {
			return language, true
		}
	}

	return "", false
}
