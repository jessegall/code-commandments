// Package source says which files the tool judges and finds them: a file's language from its extension,
// the walk over a tree that skips what is never source, and the paths a project excluded.
package source

import (
	"path/filepath"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
)

// Language is a language the tool reads, named by its file extension.
type Language string

// The languages, as their extensions spell them.
const (
	PHP        Language = "php"
	Vue        Language = "vue"
	TypeScript Language = "ts"
	Python     Language = "py"
	CSharp     Language = "cs"
)

// Languages is every language, in the order they are declared.
var Languages = []Language{PHP, Vue, TypeScript, Python, CSharp}

// OfFile is the language of the file at path; anything unrecognised reads as PHP.
func OfFile(path string) Language {
	for _, language := range []Language{Vue, TypeScript, Python, CSharp} {
		if strings.HasSuffix(path, "."+string(language)) {
			return language
		}
	}

	return PHP
}

// Judges says whether the file at path is in a language the tool reads.
func Judges(path string) bool {
	extension := strings.TrimPrefix(filepath.Ext(path), ".")

	for _, language := range Languages {
		if extension == string(language) {
			return true
		}
	}

	return false
}

// Engine is the engine that reads a file of this language.
func (l Language) Engine() catalog.Engine {
	switch l {
	case Vue, TypeScript:
		return catalog.Frontend
	case Python:
		return catalog.Python
	case CSharp:
		return catalog.CSharp
	default:
		return catalog.Backend
	}
}

// Comment is text as a line comment in this language.
func (l Language) Comment(text string) string {
	switch l {
	case Vue:
		return "<!-- " + text + " -->"
	case Python:
		return "# " + text
	default:
		return "// " + text
	}
}

// IsCommentLine says whether the line opens with a comment of this language, read off its delimiter, never its words.
func (l Language) IsCommentLine(line string) bool {
	opened := strings.TrimLeft(line, " \t\n\r\x00\x0B")
	switch l {
	case Vue:
		return strings.HasPrefix(opened, "<!--")
	case Python:
		return strings.HasPrefix(opened, "#")
	default:
		return strings.HasPrefix(opened, "//") || strings.HasPrefix(opened, "/*") || strings.HasPrefix(opened, "*")
	}
}

// Label is the language's name as a person writes it.
func (l Language) Label() string {
	switch l {
	case PHP:
		return "PHP"
	case Vue:
		return "Vue"
	case TypeScript:
		return "TypeScript"
	case Python:
		return "Python"
	default:
		return "C#"
	}
}

// NamedIn is the language a text names at its end (`… — in C#`), and whether it names one.
func NamedIn(text string) (Language, bool) {
	for _, language := range Languages {
		if strings.HasSuffix(strings.TrimRight(text, " \t\n\r\x00\x0B"), "— in "+language.Label()) {
			return language, true
		}
	}

	return "", false
}

// Real is the path with its links resolved and made absolute, as a bridge names it; the path itself when it
// does not exist.
func Real(path string) string {
	resolved, err := filepath.EvalSymlinks(path)
	if err != nil {
		return path
	}

	if absolute, err := filepath.Abs(resolved); err == nil {
		return absolute
	}

	return resolved
}

// engines are the languages each engine's rules read.
var engines = map[catalog.Engine][]Language{
	catalog.Backend:    {PHP},
	catalog.Frontend:   {Vue, TypeScript},
	catalog.TypeScript: {Vue, TypeScript},
	catalog.Python:     {Python},
	catalog.CSharp:     {CSharp},
}

// OfEngine are the languages the engine's rules read.
func OfEngine(engine catalog.Engine) []Language {
	return engines[engine]
}
