// Package scan reads a project's sources into the engine: it walks the roots once, hands each language's
// files to that language's bridge, and loads every stream into one codebase, the way the engines share it.
package scan

import (
	"bufio"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// Sources are a tree's source files, by language, and the roots they were walked from.
type Sources struct {
	byLanguage map[source.Language][]string
	roots      []string
	tooLarge   []string
	read       func(done, total int)
}

// largestSource is the most source a file may hold and still be read: past it a file is generated or minified, and
// its tree outgrows the line a stream carries it in.
const largestSource = 2 << 20

// longestWrittenLine is the longest line a person writes. Past it the file is minified, whatever its size: a
// bundle holds a whole program on a handful of lines, and parsing one costs the memory of everything it was built
// from while telling nobody anything, since no finding on generated code is a finding anyone can act on.
const longestWrittenLine = 5000

// smallestBundle is the size under which a line cannot be long enough to matter, so a small file is never read
// twice to look for one.
const smallestBundle = 64 << 10

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
			// A file is found again by the path it really has, so one whose links or absolute form cannot be
			// had is no entry at all: keying it by anything else would answer a later look wrongly. That is
			// stricter than resolved() below, which keeps what it was given, because this is a key.
			real, err := filepath.EvalSymlinks(file)
			if err != nil {
				continue
			}
			absolute, err := filepath.Abs(real)
			if err != nil {
				continue
			}
			given[absolute] = file
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
			if info, err := os.Stat(file); err == nil && unreadable(file, info.Size()) {
				sources.tooLarge = append(sources.tooLarge, file)

				continue
			}
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

// Within is how many files of the languages the target takes in.
func (s Sources) Within(target interface{ Includes(string) bool }, languages ...source.Language) int {
	count := 0
	for _, file := range s.Files(languages...) {
		if target.Includes(file) {
			count++
		}
	}

	return count
}

// Only are the sources of these languages alone.
func (s Sources) Only(languages ...source.Language) Sources {
	kept := Sources{byLanguage: map[source.Language][]string{}, roots: s.roots, tooLarge: s.tooLarge, read: s.read}

	for language, files := range s.byLanguage {
		if slices.Contains(languages, language) {
			kept.byLanguage[language] = files
		}
	}

	return kept
}

// Reporting is these sources telling each, as Load's bridges read every file, how many of the files they read are
// read so far, and of how many.
func (s Sources) Reporting(each func(done, total int)) Sources {
	s.read = each

	return s
}

// Bridged is how many files Load hands its bridges to read.
func (s Sources) Bridged() int {
	count := 0
	for _, read := range Bridges {
		count += s.Count(read.Languages...)
	}

	return count
}

// bridgesAtOnce is how many bridges Load runs side by side: each is a process of its own, and two keep the scan
// within two cores.
const bridgesAtOnce = 2

// bridged is what one reader's bridge answered.
type bridged struct {
	reader int
	stream *contract.Stream
	err    error
}

// Load streams every language's files through its bridge, two bridges at a time, and loads the streams into one
// codebase in the readers' order, with the facts the engine fills for PHP filled.
func (s Sources) Load() (*engine.Codebase, error) {
	var streams []*contract.Stream
	var incomplete Incomplete
	sayTooLarge(s.tooLarge)

	answered := s.bridge()
	for index, read := range Bridges {
		answer, ran := answered[index]
		if !ran {
			continue
		}
		files := s.Count(read.Languages...)
		stream, err := answer.stream, answer.err
		if leftUnread(err, files) {
			continue
		}
		if err != nil {
			stream = partly(stream, err, files)
			incomplete.Bridges = append(incomplete.Bridges, "the "+read.Label+" bridge")
		}
		if stream == nil {
			continue
		}

		if !read.InOwnOrder {
			bridge.InWalkOrder(stream, s.Files(read.Languages...))
		}
		streams = append(streams, stream)
	}

	codebase := engine.Load(streams...)
	codebase.Scanned(s.roots...)
	codebase.Rooted(s.project())
	sayUnreadable(streams)

	if len(s.byLanguage[source.PHP]) > 0 {
		php.TypesOf(codebase).Fill(codebase)
	}
	if len(incomplete.Bridges) > 0 {
		return codebase, incomplete
	}

	return codebase, nil
}

// bridge runs the bridge of every reader with files to read, bridgesAtOnce at a time, and tells each file any of them
// reads as it reads it; the answers are keyed by the reader's place in readers.
func (s Sources) bridge() map[int]bridged {
	finished := make(chan bridged)
	slots := make(chan struct{}, bridgesAtOnce)
	running := 0
	tally := s.tally()
	for index, read := range Bridges {
		if s.Count(read.Languages...) == 0 {
			continue
		}
		running++
		go func() {
			slots <- struct{}{}
			defer func() { <-slots }()
			stream, err := read.Read(s.roots, s.Files(read.Languages...), tally)
			finished <- bridged{index, stream, err}
		}()
	}

	answered := map[int]bridged{}
	for range running {
		answer := <-finished
		answered[answer.reader] = answer
	}

	return answered
}

// Incomplete is a load a bridge broke off: the codebase holds every other language, and what the broken bridge
// wrote before it stopped.
type Incomplete struct {
	Bridges []string
}

func (i Incomplete) Error() string {
	return strings.Join(i.Bridges, ", ") + " stopped before it read every file"
}

// partly is what a broken bridge read before it stopped, said on STDERR with why, so every other file is still
// judged; nothing when it stopped before its header.
func partly(stream *contract.Stream, err error, files int) *contract.Stream {
	if stream == nil || stream.Header.Language == "" {
		fmt.Fprintf(os.Stderr, "⚠ %d file(s) left unread, every other language is still judged — %v\n", files, err)

		return nil
	}
	fmt.Fprintf(os.Stderr, "⚠ %d of %d file(s) left unread, the rest is still judged — %v\n", files-len(stream.Files), files, err)

	return stream
}

// tally is told each file a bridge reads, from any of the bridges at once, and tells the count to whoever Reporting
// named; nothing is told when none was.
func (s Sources) tally() func() {
	if s.read == nil {
		return func() {}
	}
	var lock sync.Mutex
	done, total := 0, s.Bridged()

	return func() {
		lock.Lock()
		defer lock.Unlock()
		done++
		s.read(min(done, total), total)
	}
}

// project is the repository the first root lies in, the folder a rule's path pattern is read from; none outside one.
func (s Sources) project() string {
	if len(s.roots) == 0 {
		return ""
	}
	root, err := filepath.Abs(s.roots[0])
	if err != nil {
		return ""
	}

	return git.Root(root)
}

// Files are the walked files of these languages, in the order the walk met them: what Load hands the bridge that
// reads them, and what a service warming that bridge asks it for.
func (s Sources) Files(languages ...source.Language) []string {
	var files []string

	for _, language := range languages {
		files = append(files, s.byLanguage[language]...)
	}

	return files
}

// keptFor is the bridge of the kind a session keeps up for the project the run works on, when every root lies in it.
func keptFor(kind string, roots []string) (*bridge.Server, bool) {
	cwd, _ := os.Getwd()
	project := workspace.ProjectRoot(cwd)
	if !within(project, roots) {
		return nil, false
	}

	return bridge.Service(kind, project)
}

// sayUnreadable says, on STDERR, which classes the scanned project's own loader failed on: each stays outside the
// scan, and the run says why rather than letting it vanish.
func sayUnreadable(streams []*contract.Stream) {
	for _, stream := range streams {
		if stream.Program == nil {
			continue
		}
		// What is wrong and what to run stand on the first line, and why it matters on the second: one line
		// carrying both ran to 230 characters, which is past what a reader takes in and past what a log tail or
		// a card can show.
		if stream.Program.Loader != "" {
			fmt.Fprintf(os.Stderr, "⚠ no class of a package is read: %s\n", stream.Program.Loader)
			fmt.Fprintln(os.Stderr, "  ↳ a method a package's interface fixes reads as the class's own, so a rule that would excuse it cannot")
		}
		for _, unreadable := range stream.Program.Unreadable {
			fmt.Fprintf(os.Stderr, "⚠ %s could not be loaded by the project's own loader (%s), so it is read as outside the scan\n", unreadable.Symbol, unreadable.Reason)
		}
	}
}

// unreadable says whether the file is generated rather than written: more source than a person writes, or a line
// longer than one. Either way its tree costs what the program it was built from costs, and says nothing back.
func unreadable(file string, size int64) bool {
	if size > largestSource {
		return true
	}

	return size >= smallestBundle && holdsALongLine(file)
}

// holdsALongLine says whether any line of the file is longer than a person writes, reading no further than the
// line that proves it.
func holdsALongLine(file string) bool {
	open, err := os.Open(file)
	if err != nil {
		return false
	}
	defer open.Close()

	reader := bufio.NewReader(open)
	written := 0

	for {
		piece, more, err := reader.ReadLine()
		if err != nil {
			return false
		}

		written += len(piece)
		if written > longestWrittenLine {
			return true
		}
		if !more {
			written = 0
		}
	}
}

// sayTooLarge names, on STDERR, each file left unread as generated; every other file is still judged.
func sayTooLarge(files []string) {
	for _, file := range files {
		fmt.Fprintf(os.Stderr, "⚠ %s is left unread: it is generated or minified — more than %d MB of source, or a line longer than %d characters; exclude it in the config\n", file, largestSource>>20, longestWrittenLine)
	}
}

// leftUnread says, on STDERR, that a language's files go unread because its bridge cannot run on this machine, and
// whether they do: every other language is still judged.
func leftUnread(err error, files int) bool {
	if !isUnavailable(err) {
		return false
	}
	fmt.Fprintf(os.Stderr, "⚠ %d file(s) left unread — %v\n", files, err)

	return true
}

// isUnavailable says whether the error is a bridge that could not be had: one that is not there, or one that
// started and then died. What it was asked to read goes unread, and every other language is still judged.
func isUnavailable(err error) bool {
	var unavailable bridge.Unavailable

	return errors.As(err, &unavailable)
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
