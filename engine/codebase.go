// Package engine is the one engine every language is judged through: a codebase read from the generic tree, a fluent query over its nodes, and matches that know their file:line.
package engine

import (
	"bytes"
	"crypto/sha256"
	"fmt"
	"os"
	"slices"
	"strings"
	"sync"
	"sync/atomic"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/walk"
)

// Codebase is every file the bridges wrote, each language's stream side by side.
type Codebase struct {
	streams   []*contract.Stream
	files     []*File
	read      func(path string) ([]byte, error)
	print     func(path string) ([32]byte, error)
	analyses  *sync.Map
	over      *Codebase
	carries   map[any]bool
	scanned   []string
	project   string
	reporting map[string]bool
	fills     *deferredFill
}

// deferredFill is the filling a codebase read for judging puts off until a rule is really asked: a run whose every answer is remembered never fills at all.
type deferredFill struct {
	mu     sync.Mutex
	root   *Codebase
	whole  bool
	filled map[string]bool
}

func (d *deferredFill) fillAll() {
	d.mu.Lock()
	defer d.mu.Unlock()

	if d.whole {
		return
	}
	d.whole = true

	if len(d.filled) == 0 {
		d.root.fill(nil)

		return
	}

	every := make([]string, 0, len(d.root.files))
	for _, file := range d.root.files {
		every = append(every, file.Path)
	}
	d.fill(every)
}

func (d *deferredFill) fillFor(paths []string) {
	d.mu.Lock()
	defer d.mu.Unlock()

	if !d.whole {
		d.fill(paths)
	}
}

func (d *deferredFill) fill(paths []string) {
	left := slices.DeleteFunc(slices.Clone(paths), func(path string) bool { return d.filled[path] })
	if len(left) == 0 {
		return
	}

	for _, path := range left {
		d.filled[path] = true
	}
	d.root.fill(left)
}

// Filler fills the facts the engine owns for one language, once every file of the codebase has been read.
type Filler func(*Codebase, []string)

func Fills(language contract.Language, fill Filler) {
	registers(language, func(row *spoken) { row.fill = fill })
}

func FillsFor(language contract.Language) bool {
	return spokenIn(language).fill != nil
}

func Untouched() {
	if !contract.Untouched() {
		panic("a fact was written into the Facts every node without any shares; write it through Node.Own")
	}
}

// derived is one thing a codebase derived from its files, built once however many readers ask for it at the same moment.
type derived struct {
	building sync.Mutex
	held     atomic.Pointer[any]
}

func (c *Codebase) derivedUnder(key any) *derived {
	if slot, known := c.analyses.Load(key); known {
		return slot.(*derived)
	}

	slot, _ := c.analyses.LoadOrStore(key, &derived{})

	return slot.(*derived)
}

func (c *Codebase) kept(key any) (any, bool) {
	slot, known := c.analyses.Load(key)
	if !known {
		return nil, false
	}

	held := slot.(*derived).held.Load()
	if held == nil {
		return nil, false
	}

	return *held, true
}

func Keep[T any](c *Codebase, key any, value T) {
	slot := c.derivedUnder(key)
	slot.building.Lock()
	defer slot.building.Unlock()

	var held any = value
	slot.held.Store(&held)
}

func Analysis[T any](c *Codebase, key any, build func(*Codebase) T) T {
	slot := c.derivedUnder(key)
	if held := slot.held.Load(); held != nil {
		return (*held).(T)
	}

	slot.building.Lock()
	defer slot.building.Unlock()

	if held := slot.held.Load(); held != nil {
		return (*held).(T)
	}

	held, carried := c.carriedFrom(key)
	if !carried {
		CountedAnalysis()
		held = build(c)
	}
	slot.held.Store(&held)

	return held.(T)
}

func (c *Codebase) carriedFrom(key any) (any, bool) {
	if c.over == nil || !c.carries[key] {
		return nil, false
	}

	return c.over.kept(key)
}

func (c *Codebase) Carries(over *Codebase, keys ...any) {
	c.over = over
	c.carries = map[any]bool{}
	for _, key := range keys {
		c.carries[key] = true
	}
}

// File is one source file of a stream, with its bytes read on first need.
type File struct {
	*contract.File
	codebase *Codebase
	stream   *contract.Stream
	read     func(path string) ([]byte, error)
	once     sync.Once
	source   []byte
	err      error
	lines    sync.Once
	own      map[int]contract.Comment
	attached sync.Once
	byNode   map[int][]contract.Comment
	printing sync.Once
	contents uint64
	printed  bool

	outlining sync.Once
	outline   uint64
	freezing  sync.Once
	frozen    bool
}

func Load(streams ...*contract.Stream) *Codebase {
	codebase := onDisk(built(os.ReadFile, streams...))
	codebase.fill(nil)

	return codebase
}

// UnfilledOnDisk is a codebase of files read from disk, filled when a rule first asks.
func UnfilledOnDisk(streams ...*contract.Stream) *Codebase {
	codebase := onDisk(built(os.ReadFile, streams...))
	codebase.fills = &deferredFill{root: codebase, filled: map[string]bool{}}

	return codebase
}

// onDisk takes each file's print from the walker, which read it once for every part of the run that asks.
func onDisk(codebase *Codebase) *Codebase {
	codebase.print = walk.Print

	return codebase
}

// printedThrough is the print of what the reader answers for a path.
func printedThrough(read func(path string) ([]byte, error)) func(path string) ([32]byte, error) {
	return func(path string) ([32]byte, error) {
		source, err := read(path)
		if err != nil {
			return [32]byte{}, err
		}

		return sha256.Sum256(source), nil
	}
}

func ReadThrough(contents map[string]string) func(path string) ([]byte, error) {
	return func(path string) ([]byte, error) {
		if drafted, ok := contents[path]; ok {
			return []byte(drafted), nil
		}

		return os.ReadFile(path)
	}
}

func New(read func(path string) ([]byte, error), streams ...*contract.Stream) *Codebase {
	codebase := built(read, streams...)
	codebase.fill(nil)

	return codebase
}

func Unfilled(read func(path string) ([]byte, error), streams ...*contract.Stream) *Codebase {
	codebase := built(read, streams...)
	codebase.fills = &deferredFill{root: codebase, filled: map[string]bool{}}

	return codebase
}

func (c *Codebase) Filled() {
	if c.fills != nil {
		c.fills.fillAll()
	}
}

func (c *Codebase) FilledFor(paths []string) {
	if c.fills != nil {
		c.fills.fillFor(paths)
	}
}

func built(read func(path string) ([]byte, error), streams ...*contract.Stream) *Codebase {
	codebase := &Codebase{streams: streams, read: read, print: printedThrough(read), analyses: &sync.Map{}}
	for _, stream := range streams {
		for _, file := range stream.Files {
			_ = file.Linked()
			codebase.files = append(codebase.files, &File{File: file, codebase: codebase, stream: stream, read: read})
		}
	}

	return codebase
}

func Filling(changed []string, streams ...*contract.Stream) *Codebase {
	codebase := onDisk(built(os.ReadFile, streams...))
	codebase.fill(changed)

	return codebase
}

func (c *Codebase) fill(changed []string) {
	filled := map[contract.Language]bool{}
	for _, stream := range c.streams {
		if fill := spokenIn(stream.Header.Language).fill; fill != nil && !filled[stream.Header.Language] {
			filled[stream.Header.Language] = true
			fill(c, changed)
		}
	}
	Untouched()
}

func FromString(stream string, sources map[string]string) (*Codebase, error) {
	read, err := contract.ReadAll(strings.NewReader(stream))
	if err != nil {
		return nil, err
	}

	return New(func(path string) ([]byte, error) {
		source, ok := sources[path]
		if !ok {
			return nil, fmt.Errorf("no source given for %s", path)
		}

		return []byte(source), nil
	}, read), nil
}

func (c *Codebase) Read(path string) ([]byte, error) {
	return c.read(path)
}

func (c *Codebase) Files() []*File {
	return c.files
}

func (c *Codebase) Streams() []*contract.Stream {
	return c.streams
}

// partOf is the key a codebase keeps one language's part of itself under, so every reader of that part shares its files and its analyses.
type partOf string

func (c *Codebase) Of(languages ...contract.Language) *Codebase {
	whole := Analysis(c, partNamed(languages), func(c *Codebase) *Codebase { return c.narrowedTo(languages) })

	part := *whole
	part.reporting = c.reporting

	return &part
}

func partNamed(languages []contract.Language) partOf {
	if len(languages) == 1 {
		return partOf(languages[0])
	}

	named := ""
	for _, language := range languages {
		named += string(language) + " "
	}

	return partOf(named)
}

func (c *Codebase) narrowedTo(languages []contract.Language) *Codebase {
	CountedWalk(len(c.files))

	part := &Codebase{read: c.read, print: c.print, analyses: &sync.Map{}, fills: c.fills}
	for _, stream := range c.streams {
		if slices.Contains(languages, stream.Header.Language) {
			part.streams = append(part.streams, stream)
		}
	}
	for _, file := range c.files {
		if slices.Contains(languages, file.Language()) {
			part.files = append(part.files, file)
		}
	}

	return part
}

func (c *Codebase) Reporting(paths ...string) *Codebase {
	narrowed := map[string]bool{}
	for _, path := range paths {
		narrowed[path] = true
	}

	part := *c
	part.reporting = narrowed

	return &part
}

func (c *Codebase) opened() []*File {
	CountedWalk(len(c.files))

	if len(c.reporting) == 0 {
		return c.files
	}

	var narrowed []*File
	for _, file := range c.files {
		if c.reporting[file.Path] {
			narrowed = append(narrowed, file)
		}
	}

	return narrowed
}

func (c *Codebase) Declarations(symbol string) []Match {
	return Analysis(c, "declarations", declarationsOf)[symbol]
}

func declarationsOf(c *Codebase) map[string][]Match {
	index := map[string][]Match{}
	for _, file := range c.files {
		for _, node := range file.Nodes() {
			if node.Symbol != "" {
				index[node.Symbol] = append(index[node.Symbol], Match{node: node, file: file})
			}
		}
	}

	return index
}

func (c *Codebase) Program(language contract.Language) (*contract.Program, bool) {
	for _, stream := range c.streams {
		if stream.Header.Language == language && stream.Program != nil {
			return stream.Program, true
		}
	}

	return nil, false
}

func (f *File) Source() ([]byte, error) {
	f.once.Do(func() {
		CountedFile()

		f.source, f.err = f.read(f.Path)
		if f.err != nil {
			return
		}

		if reaches := f.File.Reaches(); len(f.source) < reaches {
			f.source, f.err = nil, fmt.Errorf(
				"%s is %d bytes and the parse of it reaches %d, so it has changed since it was read",
				f.Path, len(f.source), reaches)
		}
	})

	return f.source, f.err
}

func (f *File) Alone() *Codebase {
	stream := &contract.Stream{Header: f.stream.Header, Files: []*contract.File{f.File}, Program: f.stream.Program}
	alone := New(f.read, stream)
	alone.scanned, alone.project, alone.print = f.codebase.scanned, f.codebase.project, f.codebase.print

	return alone
}

func (f *File) Codebase() *Codebase {
	return f.codebase
}

func (f *File) Language() contract.Language {
	return f.File.Language
}

func (f *File) Match(id int) Match {
	node, ok := f.Node(id)
	if !ok {
		return Match{}
	}

	return Match{node: node, file: f}
}

const CommentKind = "Comment"

func (f *File) CommentMatch(comment contract.Comment, line int) Match {
	span := comment.Span
	span.Line = line

	return Match{node: contract.NewNode(contract.Node{ID: -1, Kind: CommentKind, Role: "other", Span: span, Name: comment.Kind}), file: f}
}

func (f *File) Comments(node *contract.Node) []contract.Comment {
	f.attached.Do(f.indexComments)

	return f.byNode[node.ID]
}

func (f *File) indexComments() {
	f.byNode = map[int][]contract.Comment{}
	for _, comment := range f.File.Comments {
		if comment.Attached != nil {
			f.byNode[*comment.Attached] = append(f.byNode[*comment.Attached], comment)
		}
	}
}

func (f *File) CommentsAbove(node *contract.Node) []contract.Comment {
	f.lines.Do(f.findOwnLineComments)
	var run []contract.Comment
	for line := node.Span.Line - 1; ; line-- {
		comment, ok := f.own[line]
		if !ok {
			return run
		}
		run = append([]contract.Comment{comment}, run...)
	}
}

func (f *File) isInsideItsNode(comment contract.Comment) bool {
	if comment.Attached == nil {
		return false
	}
	node, ok := f.Node(*comment.Attached)

	return ok && node.Span.Start <= comment.Span.Start
}

func (f *File) findOwnLineComments() {
	f.own = map[int]contract.Comment{}
	source, err := f.Source()
	if err != nil {
		return
	}
	for _, comment := range f.File.Comments {
		if f.isInsideItsNode(comment) {
			continue
		}
		lineStart := bytes.LastIndexByte(source[:comment.Span.Start], '\n') + 1
		if len(bytes.TrimSpace(source[lineStart:comment.Span.Start])) == 0 {
			f.own[comment.Span.Line] = comment
		}
	}
}

var carried []func(changed []string) (any, bool)

func Carrying(over any, untouchedBy func(changed []string) bool) {
	carried = append(carried, func(changed []string) (any, bool) { return over, untouchedBy(changed) })
}

func CarriedOver(changed []string) []any {
	var keys []any
	for _, each := range carried {
		if key, carries := each(changed); carries {
			keys = append(keys, key)
		}
	}

	return keys
}
