// Package source says which files the tool judges and finds them: a file's language from its extension, the walk over a tree that skips what is never source, and the paths a project excluded.
package source

import (
	"os"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/walk"
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

// facts is what the CLI knows of a language beyond the contract's own facts of it: the name its stream writes, the
// engines that judge it, and the files that declare a project of it where only such a project is read.
type facts struct {
	written   contract.Language
	engines   []catalog.Engine
	manifests []string
}

var described = map[Language]facts{
	PHP:        {written: contract.PHP, engines: []catalog.Engine{catalog.PHP}},
	Vue:        {written: contract.Vue, engines: []catalog.Engine{catalog.Vue, catalog.TypeScript}},
	TypeScript: {written: contract.TypeScript, engines: []catalog.Engine{catalog.Vue, catalog.TypeScript}},
	Python:     {written: contract.Python, engines: []catalog.Engine{catalog.Python}},
	CSharp:     {written: contract.CSharp, engines: []catalog.Engine{catalog.CSharp}},
	Go:         {written: contract.Go, engines: []catalog.Engine{catalog.Go}, manifests: []string{"go.mod"}},
}

func Of(stream contract.Language) (Language, bool) {
	for named, held := range described {
		if held.written == stream {
			return named, true
		}
	}

	return "", false
}

func (l Language) Written() contract.Language {
	return described[l].written
}

func (l Language) Extensions() []string {
	return contract.ExtensionsOf(l.Written())
}

func OfFile(path string) Language {
	stream, known := contract.LanguageOfPath(path)
	if !known {
		return PHP
	}
	named, _ := Of(stream)

	return named
}

func (l Language) DeclaredIn(root string) bool {
	named := described[l].manifests
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
	walk.Folders(root, Excluded{}, func(dir string) {
		found = found || slices.ContainsFunc(named, func(name string) bool { return walk.IsFile(filepath.Join(dir, name)) })
	})

	return found
}

func Judges(path string) bool {
	_, named := contract.LanguageOfPath(path)

	return named
}

func (l Language) Engines() []catalog.Engine {
	return described[l].engines
}

func (l Language) Comment(text string) string {
	return contract.CommentOf(l.Written(), text)
}

func (l Language) Label() string {
	return contract.Label(l.Written())
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
		named, _ := Of(language)
		read = append(read, named)
	}

	return read
}
