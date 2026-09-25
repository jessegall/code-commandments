// Package scan reads a project's sources into the engine: it walks the roots once, hands each language's
// files to that language's bridge, and loads every stream into one codebase, the way the engines share it.
package scan

import (
	"slices"
	"sort"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/frontend"
	"github.com/jessegall/code-commandments/engine/php"
)

// Sources are a tree's source files, by language.
type Sources map[source.Language][]string

// Walk finds the source files under every root, less what is excluded, each file once.
func Walk(roots []string, excluded source.Excluded) Sources {
	sources := Sources{}
	seen := map[string]bool{}

	for _, root := range roots {
		for _, file := range source.Sources(root, excluded) {
			if seen[file] {
				continue
			}

			seen[file] = true
			language := source.OfFile(file)
			sources[language] = append(sources[language], file)
		}
	}

	for language := range sources {
		sort.Strings(sources[language])
	}

	return sources
}

// Count is how many files the languages have.
func (s Sources) Count(languages ...source.Language) int {
	count := 0

	for _, language := range languages {
		count += len(s[language])
	}

	return count
}

// Only are the sources of these languages alone.
func (s Sources) Only(languages ...source.Language) Sources {
	kept := Sources{}

	for language, files := range s {
		if slices.Contains(languages, language) {
			kept[language] = files
		}
	}

	return kept
}

// Load streams every language's files through its bridge and loads the streams into one codebase, with
// the facts the engine fills for PHP filled.
func (s Sources) Load() (*engine.Codebase, error) {
	var streams []*contract.Stream

	for _, read := range readers {
		files := s.Count(read.languages...)
		if files == 0 {
			continue
		}

		stream, err := read.stream(s.files(read.languages))
		if err != nil {
			return nil, err
		}

		streams = append(streams, stream)
	}

	codebase := engine.Load(streams...)

	if len(s[source.PHP]) > 0 {
		php.TypesOf(codebase).Fill(codebase)
	}

	return codebase, nil
}

func (s Sources) files(languages []source.Language) []string {
	var files []string

	for _, language := range languages {
		files = append(files, s[language]...)
	}

	return files
}

// reader is one bridge and the languages it reads.
type reader struct {
	languages []source.Language
	stream    func(files []string) (*contract.Stream, error)
}

// readers are the bridges, each with the languages it reads. C# is read once its engine ships its bridge.
var readers = []reader{
	{[]source.Language{source.PHP}, func(files []string) (*contract.Stream, error) {
		return php.Here().Stream(files...)
	}},
	{[]source.Language{source.Vue, source.TypeScript}, func(files []string) (*contract.Stream, error) {
		return frontend.Here().Stream(files...)
	}},
	{[]source.Language{source.Python}, func(files []string) (*contract.Stream, error) {
		command, err := bridge.Mypy()
		if err != nil {
			return nil, err
		}

		return bridge.Once(command, files...)
	}},
}
