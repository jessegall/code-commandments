// Package engine is the one engine every language is judged through: a codebase read from the generic tree, a fluent query over its nodes, and matches that know their file:line.
package engine

import (
	"bytes"
	"fmt"
	"os"
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
)

// Codebase is every file the bridges wrote, each language's stream side by side.
type Codebase struct {
	streams   []*contract.Stream
	files     []*File
	read      func(path string) ([]byte, error)
	analyses  *sync.Map
	scanned   []string
	project   string
	reporting map[string]bool
}

// Filler fills the facts the engine owns for one language, once every file of the codebase has been read.
type Filler func(*Codebase, []string)

var fillers = map[contract.Language]Filler{}

func Fills(language contract.Language, fill Filler) {
	fillers[language] = fill
}

func FillsFor(language contract.Language) bool {
	_, registered := fillers[language]

	return registered
}

func Untouched() {
	if !contract.Untouched() {
		panic("a fact was written into the Facts every node without any shares; write it through Node.Own")
	}
}

func Keep[T any](c *Codebase, key any, value T) {
	c.analyses.Store(key, value)
}

func Analysis[T any](c *Codebase, key any, build func(*Codebase) T) T {
	if held, ok := c.analyses.Load(key); ok {
		return held.(T)
	}
	held, _ := c.analyses.LoadOrStore(key, build(c))

	return held.(T)
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
}

func Load(streams ...*contract.Stream) *Codebase {
	return New(os.ReadFile, streams...)
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

func built(read func(path string) ([]byte, error), streams ...*contract.Stream) *Codebase {
	codebase := &Codebase{streams: streams, read: read, analyses: &sync.Map{}}
	for _, stream := range streams {
		for _, file := range stream.Files {
			codebase.files = append(codebase.files, &File{File: file, codebase: codebase, stream: stream, read: read})
		}
	}

	return codebase
}

func Filling(changed []string, streams ...*contract.Stream) *Codebase {
	codebase := built(os.ReadFile, streams...)
	codebase.fill(changed)

	return codebase
}

func (c *Codebase) fill(changed []string) {
	filled := map[contract.Language]bool{}
	for _, stream := range c.streams {
		if fill, ok := fillers[stream.Header.Language]; ok && !filled[stream.Header.Language] {
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

func (c *Codebase) Of(languages ...contract.Language) *Codebase {
	part := &Codebase{read: c.read, analyses: &sync.Map{}, reporting: c.reporting}
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
	alone.scanned, alone.project = f.codebase.scanned, f.codebase.project

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
	var attached []contract.Comment
	for _, comment := range f.File.Comments {
		if comment.Attached != nil && *comment.Attached == node.ID {
			attached = append(attached, comment)
		}
	}

	return attached
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
