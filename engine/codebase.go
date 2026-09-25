// Package engine is the one engine every language is judged through: a codebase read from the
// generic tree, a fluent query over its nodes, and matches that know their file:line.
package engine

import (
	"fmt"
	"os"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
)

// Codebase is every file the bridges wrote, each language's stream side by side.
type Codebase struct {
	streams  []*contract.Stream
	files    []*File
	analyses sync.Map
}

// Filler fills the facts the engine owns for one language, once every file of the codebase has been read.
type Filler func(*Codebase)

var fillers = map[contract.Language]Filler{}

// Fills enrols the filler of one language's engine-owned facts, from that language's own package.
func Fills(language contract.Language, fill Filler) {
	fillers[language] = fill
}

// Analysis is one whole-program analysis of the codebase, built by build on first use and kept.
func Analysis[T any](c *Codebase, key string, build func(*Codebase) T) T {
	if held, ok := c.analyses.Load(key); ok {
		return held.(T)
	}
	held, _ := c.analyses.LoadOrStore(key, build(c))

	return held.(T)
}

// File is one source file of a stream, with its bytes read on first need.
type File struct {
	*contract.File
	stream *contract.Stream
	read   func(path string) ([]byte, error)
	once   sync.Once
	source []byte
	err    error
}

// Load is the codebase the streams describe, its sources read from disk.
func Load(streams ...*contract.Stream) *Codebase {
	return New(os.ReadFile, streams...)
}

// New is the codebase the streams describe, its sources read through read.
func New(read func(path string) ([]byte, error), streams ...*contract.Stream) *Codebase {
	codebase := &Codebase{streams: streams}
	for _, stream := range streams {
		for _, file := range stream.Files {
			codebase.files = append(codebase.files, &File{File: file, stream: stream, read: read})
		}
	}
	filled := map[contract.Language]bool{}
	for _, stream := range streams {
		if fill, ok := fillers[stream.Header.Language]; ok && !filled[stream.Header.Language] {
			filled[stream.Header.Language] = true
			fill(codebase)
		}
	}

	return codebase
}

// FromString is the codebase one stream's JSON lines describe, its sources given by path.
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

// Files is every file, in the order the streams wrote them.
func (c *Codebase) Files() []*File {
	return c.files
}

// Of is the part of the codebase one language's stream holds.
func (c *Codebase) Of(language contract.Language) *Codebase {
	part := &Codebase{}
	for _, stream := range c.streams {
		if stream.Header.Language == language {
			part.streams = append(part.streams, stream)
		}
	}
	for _, file := range c.files {
		if file.stream.Header.Language == language {
			part.files = append(part.files, file)
		}
	}

	return part
}

// Program is the facts about the whole program one language's bridge wrote, if it wrote any.
func (c *Codebase) Program(language contract.Language) (*contract.Program, bool) {
	for _, stream := range c.streams {
		if stream.Header.Language == language && stream.Program != nil {
			return stream.Program, true
		}
	}

	return nil, false
}

// Source is the file's bytes, read once.
func (f *File) Source() ([]byte, error) {
	f.once.Do(func() {
		f.source, f.err = f.read(f.Path)
	})

	return f.source, f.err
}

// Language is the language of the stream that holds the file.
func (f *File) Language() contract.Language {
	return f.stream.Header.Language
}

// Match is the file's node with the id, such as the node a comment is attached to; no node when the file holds none.
func (f *File) Match(id int) Match {
	node, ok := f.Node(id)
	if !ok {
		return Match{}
	}

	return Match{node: node, file: f}
}

// Comments is every comment attached to the node.
func (f *File) Comments(node *contract.Node) []contract.Comment {
	var attached []contract.Comment
	for _, comment := range f.File.Comments {
		if comment.Attached != nil && *comment.Attached == node.ID {
			attached = append(attached, comment)
		}
	}

	return attached
}
