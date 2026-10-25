// Package source says which files the tool judges and finds them: a file's language from its extension,
// the walk over a tree that skips what is never source, and the paths a project excluded.
package source

import (
	"io/fs"
	"os"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
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
	Go         Language = "go"
)

// Languages is every language, in the order they are declared.
var Languages = []Language{PHP, Vue, TypeScript, Python, CSharp, Go}

// written are the languages of the stream by the name their extension spells them with here, so the one table
// of extensions, which the contract holds below every reader, answers for this spelling too.
var written = map[contract.Language]Language{
	contract.PHP:        PHP,
	contract.Vue:        Vue,
	contract.TypeScript: TypeScript,
	contract.Python:     Python,
	contract.CSharp:     CSharp,
	contract.Go:         Go,
}

// Written is the language of the stream this one is.
func (l Language) Written() contract.Language {
	for stream, named := range written {
		if named == l {
			return stream
		}
	}

	return contract.PHP
}

// Extensions is every extension this language's files carry, its own first: what a reader is told the tool
// reads, from the one table every reader of an extension reads.
func (l Language) Extensions() []string {
	return contract.ExtensionsOf(l.Written())
}

// OfFile is the language of the file at path; anything unrecognised reads as PHP, which is the one language
// whose files carry extensions the tool does not list (.inc, .module).
//
// Every language the tool reads is asked before that default, so a file of one is never read as PHP: a .go file
// judged by the PHP rules is a wrong answer nothing reports, which is worse than no answer at all.
func OfFile(path string) Language {
	stream, named := contract.LanguageOfPath(path)
	if !named {
		return PHP
	}

	return written[stream]
}

// manifests are the files whose presence says a tree is written in the language, for a language where a file of
// it alone does not say so.
//
// Go is such a language. A `.go` file turns up in a PHP shop as a one-off script, a vendored helper, a tool
// somebody dropped in scripts/, and judging it would hand that project a set of findings it never asked for from
// a release it only upgraded. A Go project, on the other hand, always declares itself: a module has a go.mod, and
// has had to since Go 1.16.
var manifests = map[Language][]string{
	Go: {"go.mod"},
}

// DeclaredIn says whether the tree at root is written in this language: either the language needs no declaring,
// or the file that declares it is there — at the root, above it, or under it, since a judge may be pointed at one
// folder of a module, at the module itself, or at a tree holding several.
func (l Language) DeclaredIn(root string) bool {
	named := manifests[l]
	if len(named) == 0 {
		return true
	}

	return declaredAt(root, named) || declaredAbove(root, named) || declaredUnder(root, named)
}

// declaredAt says whether one of the files is in the folder itself.
func declaredAt(folder string, named []string) bool {
	for _, name := range named {
		if _, err := os.Stat(filepath.Join(folder, name)); err == nil {
			return true
		}
	}

	return false
}

// declaredAbove says whether a folder above this one holds the declaring file, which is where a module keeps it
// when a judge is pointed at one folder of it.
func declaredAbove(root string, named []string) bool {
	at := root
	for {
		parent := filepath.Dir(at)
		if parent == at {
			return false
		}
		if declaredAt(parent, named) {
			return true
		}
		at = parent
	}
}

// declaredUnder says whether a folder under this one holds the declaring file, which is where a tree of several
// modules keeps them. It stops at the first.
func declaredUnder(root string, named []string) bool {
	found := false
	filepath.WalkDir(root, func(path string, entry fs.DirEntry, err error) error {
		switch {
		case err != nil:
			return nil
		case found:
			return fs.SkipAll
		case entry.IsDir():
			if path != root && !descends(path, Excluded{}) {
				return fs.SkipDir
			}

			return nil
		case slices.Contains(named, entry.Name()):
			found = true

			return fs.SkipAll
		}

		return nil
	})

	return found
}

// Judges says whether the file at path is in a language the tool reads.
func Judges(path string) bool {
	_, named := contract.LanguageOfPath(path)

	return named
}

// Engine is the engine that reads a file of this language.
func (l Language) Engine() catalog.Engine {
	switch l {
	case Vue, TypeScript:
		return catalog.Vue
	case Python:
		return catalog.Python
	case CSharp:
		return catalog.CSharp
	case Go:
		return catalog.Go
	default:
		return catalog.PHP
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
	case Go:
		return "Go"
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
	catalog.PHP:        {PHP},
	catalog.Vue:        {Vue, TypeScript},
	catalog.TypeScript: {Vue, TypeScript},
	catalog.Python:     {Python},
	catalog.CSharp:     {CSharp},
	catalog.Go:         {Go},
}

// OfEngine are the languages the engine's rules read.
func OfEngine(engine catalog.Engine) []Language {
	return engines[engine]
}
