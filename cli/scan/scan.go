// Package scan reads a project's sources into the engine: it walks the roots once, hands each language's files to that language's bridge, and loads every stream into one codebase, the way the engines share it.
package scan

import (
	"bufio"
	"crypto/sha256"
	"encoding/hex"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	_ "github.com/jessegall/code-commandments/engine/php"
)

// Sources are a tree's source files, by language, and the roots they were walked from.
type Sources struct {
	byLanguage map[source.Language][]string
	roots      []string
	tooLarge   []string
	read       func(done, total int)
	atOnce     int
}

// ReadingAtOnce is these sources read by as many bridge processes per language side by side, each a share of the files.
func (s Sources) ReadingAtOnce(processes int) Sources {
	s.atOnce = processes

	return s
}

const largestSource = 2 << 20

const longestWrittenLine = 5000

const smallestBundle = 64 << 10

// Given is each walked file's resolved path, back to the path it was walked as: a bridge answers with resolved paths, and a report names a file the way the run was asked for it.
type Given map[string]string

func (g Given) Of(path string) string {
	if given, walked := g[path]; walked {
		return given
	}

	return path
}

func (s Sources) GivenOf() Given {
	given := Given{}

	for _, files := range s.byLanguage {
		for _, file := range files {
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

func OneFile(path string) Sources {
	return Sources{byLanguage: map[source.Language][]string{source.OfFile(path): {path}}, roots: []string{filepath.Dir(path)}}
}

func (s Sources) Roots() []string {
	return s.roots
}

func (s Sources) Checkout() string {
	return s.project()
}

func (s Sources) Count(languages ...source.Language) int {
	count := 0

	for _, language := range languages {
		count += len(s.byLanguage[language])
	}

	return count
}

func (s Sources) Within(target interface{ Includes(string) bool }, languages ...source.Language) int {
	count := 0
	for _, file := range s.Files(languages...) {
		if target.Includes(file) {
			count++
		}
	}

	return count
}

func (s Sources) Only(languages ...source.Language) Sources {
	kept := Sources{byLanguage: map[source.Language][]string{}, roots: s.roots, tooLarge: s.tooLarge, read: s.read}

	for language, files := range s.byLanguage {
		if slices.Contains(languages, language) {
			kept.byLanguage[language] = files
		}
	}

	return kept
}

func (s Sources) Reporting(each func(done, total int)) Sources {
	s.read = each

	return s
}

func (s Sources) Bridged() int {
	count := 0
	for _, read := range Bridges {
		count += s.Count(read.Languages...)
	}

	return count
}

const bridgesAtOnce = 2

// bridged is what one reader's bridge answered.
type bridged struct {
	reader int
	stream *contract.Stream
	err    error
}

func (s Sources) Load() (*engine.Codebase, error) {
	codebase, err := s.Unfilled()
	if codebase != nil {
		codebase.Filled()
	}

	return codebase, err
}

func (s Sources) Unfilled() (*engine.Codebase, error) {
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
			stayedWithin(stream, files, read.Label)
			bridge.InWalkOrder(stream, s.Files(read.Languages...))
		}
		streams = append(streams, stream)
	}

	codebase := s.rooted(engine.Unfilled(os.ReadFile, streams...))
	engine.CountedParsed(len(codebase.Files()))
	sayUnreadable(streams)

	if len(incomplete.Bridges) > 0 {
		return codebase, incomplete
	}

	return codebase, nil
}

func stayedWithin(stream *contract.Stream, given int, label string) {
	if len(stream.Files) > given {
		panic(fmt.Sprintf("the %s bridge answered with %d files and was given %d: the scan left the folders it was given", label, len(stream.Files), given))
	}
}

// bridge reads every language's files, one project's reads one run at a time so each run finds in the cache what
// the run before it read; judging runs after, outside the turn, so runs judge side by side.
func (s Sources) bridge() map[int]bridged {
	defer s.readingTurn()()

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
			stream, err := read.ReadShared(s.roots, s.Files(read.Languages...), tally, s.atOnce)
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

// Incomplete is a load a bridge broke off: the codebase holds every other language, and what the broken bridge wrote before it stopped.
type Incomplete struct {
	Bridges []string
}

func (i Incomplete) Error() string {
	return strings.Join(i.Bridges, ", ") + " stopped before it read every file"
}

func partly(stream *contract.Stream, err error, files int) *contract.Stream {
	if stream == nil || stream.Header.Language == "" {
		fmt.Fprintf(os.Stderr, "⚠ %d file(s) left unread, every other language is still judged — %v\n", files, err)

		return nil
	}
	fmt.Fprintf(os.Stderr, "⚠ %d of %d file(s) left unread, the rest is still judged — %v\n", files-len(stream.Files), files, err)

	return stream
}

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

func (s Sources) rooted(codebase *engine.Codebase) *engine.Codebase {
	codebase.Scanned(s.roots...)
	codebase.Rooted(s.project())

	return codebase
}

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

func (s Sources) JustThese(holds func(path string) bool) Sources {
	kept := Sources{byLanguage: map[source.Language][]string{}, roots: s.roots, tooLarge: s.tooLarge, read: s.read}

	for language, walked := range s.byLanguage {
		for _, file := range walked {
			if holds(file) {
				kept.byLanguage[language] = append(kept.byLanguage[language], file)
			}
		}
	}

	return kept
}

func (s Sources) Files(languages ...source.Language) []string {
	var files []string

	for _, language := range languages {
		files = append(files, s.byLanguage[language]...)
	}

	return files
}

func keptFor(kind string, roots []string) (*bridge.Server, bool) {
	project, found := projectOf(roots)
	if !found {
		return nil, false
	}

	return bridge.Service(kind, project)
}

func (s Sources) readingTurn() (release func()) {
	if _, found := projectOf(s.roots); !found || len(s.roots) == 0 {
		return func() {}
	}
	turn := ReadingTurn(s.roots[0])
	if turn == "" {
		return func() {}
	}

	began, waited := time.Now(), false
	release = atomic.HoldInTurn(turn, func() {
		waited = true
		fmt.Fprintln(os.Stderr, "· another run is reading this checkout, so this one waits for it and then reads from the cache")
	}, readingWaitedAtMost)
	if waited {
		fmt.Fprintf(os.Stderr, "· waited %s for the other run's read\n", time.Since(began).Round(time.Second))
	}

	return release
}

// ReadingTurn is the file the runs of one checkout take turns on while they read it, so the second finds the first's
// reads in the cache. Another checkout of the project has a turn of its own and reads beside them: it shares the cache
// of what was read, and holds its own mypy cache.
func ReadingTurn(checkout string) string {
	folder, root := workspace.SharedFolder(checkout), git.Root(checkout)
	if folder == "" || root == "" {
		return ""
	}
	sum := sha256.Sum256([]byte(root))

	return filepath.Join(folder, "reading", hex.EncodeToString(sum[:8])+".lock")
}

const readingWaitedAtMost = 10 * time.Minute

func projectOf(roots []string) (string, bool) {
	cwd, _ := os.Getwd()

	var named []string
	if project, told := workspace.JournalProject(); told {
		named = append(named, project)
	}
	named = append(named, workspace.SettingsRoot(workspace.ProjectRoot(cwd)))

	if len(roots) > 0 {
		named = append(named, workspace.SettingsRoot(workspace.ProjectRoot(roots[0])))
	}

	for _, project := range named {
		if within(project, roots) {
			return project, true
		}
	}

	return "", false
}

func sayUnreadable(streams []*contract.Stream) {
	for _, stream := range streams {
		if stream.Program == nil {
			continue
		}
		if stream.Program.Loader != "" {
			fmt.Fprintf(os.Stderr, "⚠ no class of a package is read: %s\n", stream.Program.Loader)
			fmt.Fprintln(os.Stderr, "  ↳ a method a package's interface fixes reads as the class's own, so a rule that would excuse it cannot")
		}
		for _, unreadable := range stream.Program.Unreadable {
			fmt.Fprintf(os.Stderr, "⚠ %s could not be loaded by the project's own loader (%s), so it is read as outside the scan\n", unreadable.Symbol, unreadable.Reason)
		}
	}
}

func unreadable(file string, size int64) bool {
	if size > largestSource {
		return true
	}

	return size >= smallestBundle && holdsALongLine(file)
}

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

func sayTooLarge(files []string) {
	for _, file := range files {
		fmt.Fprintf(os.Stderr, "⚠ %s is left unread: it is generated or minified — more than %d MB of source, or a line longer than %d characters; exclude it in the config\n", file, largestSource>>20, longestWrittenLine)
	}
}

func leftUnread(err error, files int) bool {
	if !isUnavailable(err) {
		return false
	}
	fmt.Fprintf(os.Stderr, "⚠ %d file(s) left unread — %v\n", files, err)

	return true
}

func isUnavailable(err error) bool {
	var unavailable bridge.Unavailable

	return errors.As(err, &unavailable)
}

func within(project string, roots []string) bool {
	for _, root := range roots {
		if root != project && !strings.HasPrefix(root, project+string(filepath.Separator)) {
			return false
		}
	}

	return project != ""
}

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
