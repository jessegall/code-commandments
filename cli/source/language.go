// Package source says which files the tool judges and finds them: a file's language from its extension, the walk over a tree that skips what is never source, and the paths a project excluded.
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

const (
	PHP        Language = "php"
	Vue        Language = "vue"
	TypeScript Language = "ts"
	Python     Language = "py"
	CSharp     Language = "cs"
	Go         Language = "go"
)

var Languages = []Language{PHP, Vue, TypeScript, Python, CSharp, Go}

var written = map[contract.Language]Language{
	contract.PHP:        PHP,
	contract.Vue:        Vue,
	contract.TypeScript: TypeScript,
	contract.Python:     Python,
	contract.CSharp:     CSharp,
	contract.Go:         Go,
}

func Of(stream contract.Language) (Language, bool) {
	named, known := written[stream]

	return named, known
}

func (l Language) Written() contract.Language {
	for stream, named := range written {
		if named == l {
			return stream
		}
	}

	return contract.PHP
}

func (l Language) Extensions() []string {
	return contract.ExtensionsOf(l.Written())
}

func OfFile(path string) Language {
	stream, named := contract.LanguageOfPath(path)
	if !named {
		return PHP
	}

	return written[stream]
}

var manifests = map[Language][]string{
	Go: {"go.mod"},
}

func (l Language) DeclaredIn(root string) bool {
	named := manifests[l]
	if len(named) == 0 {
		return true
	}

	return declaredAt(root, named) || declaredAbove(root, named) || declaredUnder(root, named)
}

func declaredAt(folder string, named []string) bool {
	for _, name := range named {
		if _, err := os.Stat(filepath.Join(folder, name)); err == nil {
			return true
		}
	}

	return false
}

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

func Judges(path string) bool {
	_, named := contract.LanguageOfPath(path)

	return named
}

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

func NamedIn(text string) (Language, bool) {
	for _, language := range Languages {
		if strings.HasSuffix(strings.TrimRight(text, " \t\n\r\x00\x0B"), "— in "+language.Label()) {
			return language, true
		}
	}

	return "", false
}

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

func OfEngine(engine catalog.Engine) []Language {
	var read []Language
	for _, language := range catalog.LanguagesOf(engine) {
		read = append(read, written[language])
	}

	return read
}
