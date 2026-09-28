// Package scan reads a project's sources into the engine: it walks the roots once, hands each language's
// files to that language's bridge, and loads every stream into one codebase, the way the engines share it.
package scan

import (
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"slices"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/frontend"
	"github.com/jessegall/code-commandments/engine/php"
)

// Sources are a tree's source files, by language, and the roots they were walked from.
type Sources struct {
	byLanguage map[source.Language][]string
	roots      []string
}

// Given is each walked file's resolved path, back to the path it was walked as: a bridge answers with
// resolved paths, and a report names a file the way the run was asked for it.
type Given map[string]string

// Of is the path the file was walked as; a file the walk never met keeps its path.
func (g Given) Of(path string) string {
	if given, walked := g[path]; walked {
		return given
	}

	return path
}

// GivenOf is where each of the sources' files was walked from.
func (s Sources) GivenOf() Given {
	given := Given{}

	for _, files := range s.byLanguage {
		for _, file := range files {
			if real, err := filepath.EvalSymlinks(file); err == nil {
				if absolute, err := filepath.Abs(real); err == nil {
					given[absolute] = file
				}
			}
		}
	}

	return given
}

// Walk finds the source files under every root, less what is excluded, each file once, in the order the
// walk meets them.
func Walk(roots []string, excluded source.Excluded) Sources {
	sources := Sources{byLanguage: map[source.Language][]string{}, roots: roots}
	seen := map[string]bool{}

	for _, root := range roots {
		for _, file := range source.Sources(root, excluded) {
			if seen[file] {
				continue
			}

			seen[file] = true
			language := source.OfFile(file)
			sources.byLanguage[language] = append(sources.byLanguage[language], file)
		}
	}

	return sources
}

// OneFile is the sources of one file alone, in its language, rooted at its folder.
func OneFile(path string) Sources {
	return Sources{byLanguage: map[source.Language][]string{source.OfFile(path): {path}}, roots: []string{filepath.Dir(path)}}
}

// Count is how many files the languages have.
func (s Sources) Count(languages ...source.Language) int {
	count := 0

	for _, language := range languages {
		count += len(s.byLanguage[language])
	}

	return count
}

// Only are the sources of these languages alone.
func (s Sources) Only(languages ...source.Language) Sources {
	kept := Sources{byLanguage: map[source.Language][]string{}, roots: s.roots}

	for language, files := range s.byLanguage {
		if slices.Contains(languages, language) {
			kept.byLanguage[language] = files
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

		walked := s.files(read.languages)

		stream, err := read.stream(s.roots, walked)
		if leftUnread(err, files) {
			continue
		}
		if err != nil {
			return nil, err
		}
		if stream == nil {
			continue
		}

		if !read.bridgeOrder {
			bridge.InWalkOrder(stream, walked)
		}
		streams = append(streams, stream)
	}

	codebase := engine.Load(streams...)
	codebase.Scanned(s.roots...)
	sayUnreadable(streams)

	if len(s.byLanguage[source.PHP]) > 0 {
		php.TypesOf(codebase).Fill(codebase)
	}

	return codebase, nil
}

func (s Sources) files(languages []source.Language) []string {
	var files []string

	for _, language := range languages {
		files = append(files, s.byLanguage[language]...)
	}

	return files
}

// reader is one bridge and the languages it reads: the files it writes, under the roots the walk started from. A
// reader with nothing to read them with answers no stream. A stream is put in walk order, unless the reader keeps
// the order its bridge answers in, as the PHP tool keeps C#'s, project by project.
type reader struct {
	languages   []source.Language
	stream      func(roots, files []string) (*contract.Stream, error)
	bridgeOrder bool
}

// readers are the bridges, each with the languages it reads.
var readers = []reader{
	{languages: []source.Language{source.PHP}, stream: func(_, files []string) (*contract.Stream, error) {
		return php.Here().Stream(files...)
	}},
	{languages: []source.Language{source.Vue, source.TypeScript}, stream: func(_, files []string) (*contract.Stream, error) {
		return frontend.Here().Stream(files...)
	}},
	{languages: []source.Language{source.CSharp}, stream: csharp, bridgeOrder: true},
	{languages: []source.Language{source.Python}, stream: func(_, files []string) (*contract.Stream, error) {
		command, err := bridge.Mypy()
		if err != nil {
			return nil, err
		}

		return bridge.Once(command, files...)
	}},
}

// csharp is the C# files read by the Roslyn bridge, which compiles every project under the roots so each type
// resolves and writes only the files: through the bridge a session keeps up for the project, else one of its own for
// this run. Without a bridge, or with one that fails, it answers why, and the files go unread.
func csharp(roots, files []string) (*contract.Stream, error) {
	roots, files = resolved(roots), resolved(files)
	server, err := roslyn(roots)
	if err != nil {
		return nil, err
	}
	defer server.Close()
	stream, err := server.Ask(bridge.Request{Paths: roots, Write: files})
	if err != nil {
		return nil, bridge.RoslynFailure(err)
	}

	return stream, nil
}

// roslyn is the C# bridge for the roots: the one a session keeps up for the project that holds them all, else one
// started for this run, or why there is none, a bridge that fails to start among them; a machine with no .NET SDK is
// told once that C# is judged without the frameworks' types.
func roslyn(roots []string) (*bridge.Server, error) {
	cwd, _ := os.Getwd()
	project := workspace.ProjectRoot(cwd)
	if within(project, roots) {
		if server, kept := bridge.RoslynService(project); kept {
			return server, nil
		}
	}
	command, err := bridge.Roslyn(roots...)
	if err != nil {
		return nil, err
	}
	if notice := bridge.RoslynNotice(); notice != "" {
		fmt.Fprintf(os.Stderr, "⚠ %s\n", notice)
	}
	server, err := bridge.Serve(command)
	if err != nil {
		return nil, bridge.RoslynFailure(err)
	}

	return server, nil
}

// sayUnreadable says, on STDERR, which classes the scanned project's own loader failed on: each stays outside the
// scan, and the run says why rather than letting it vanish.
func sayUnreadable(streams []*contract.Stream) {
	for _, stream := range streams {
		if stream.Program == nil {
			continue
		}
		for _, unreadable := range stream.Program.Unreadable {
			fmt.Fprintf(os.Stderr, "⚠ %s could not be loaded by the project's own loader (%s), so it is read as outside the scan\n", unreadable.Symbol, unreadable.Reason)
		}
	}
}

// leftUnread says, on STDERR, that a language's files go unread because its bridge cannot run on this machine, and
// whether they do: every other language is still judged.
func leftUnread(err error, files int) bool {
	var unavailable bridge.Unavailable
	if !errors.As(err, &unavailable) {
		return false
	}
	fmt.Fprintf(os.Stderr, "⚠ %d file(s) left unread — %v\n", files, err)

	return true
}

// within says whether every root lies in the project.
func within(project string, roots []string) bool {
	for _, root := range roots {
		if root != project && !strings.HasPrefix(root, project+string(filepath.Separator)) {
			return false
		}
	}

	return project != ""
}

// resolved is each path absolute with its links resolved, as a container mounts it and the bridge names it.
func resolved(paths []string) []string {
	var real []string

	for _, path := range paths {
		if linked, err := filepath.EvalSymlinks(path); err == nil {
			path = linked
		}
		if absolute, err := filepath.Abs(path); err == nil {
			path = absolute
		}
		real = append(real, path)
	}

	return real
}
