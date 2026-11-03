// Command census writes the observed vocabulary of every language: the kinds, roles, neutral kinds, fields,
// operators, modifiers and flags the recorded streams really carry, each with the place the census first met it.
//
// It reads only RECORDED streams, so it runs in a second and needs no bridge and no container of its own: the
// streams the contract keeps as its samples, and the whole backend and frontend fixtures as the PHP and frontend
// bridges once wrote them. A language whose fixture tree is reachable only through a bridge is therefore covered
// by its sample alone, which the committed file says in its `sources`.
//
// What a census cannot say is what a parser has never been asked to emit here. That is the declared half of the
// vocabulary, and it is the row's `vouched` that keeps the two apart.
package main

import (
	"compress/gzip"
	"encoding/json"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"slices"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/rule"
)

func main() {
	if err := write(); err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
}

// write reads every source and writes one vocabulary per language it saw.
func write() error {
	census := map[contract.Language]*counted{}
	for _, source := range rule.VocabularySources {
		stream, err := read(source)
		if err != nil {
			return fmt.Errorf("%s: %w", source, err)
		}

		for _, file := range stream.Files {
			held, seen := census[file.Language]
			if !seen {
				held = &counted{constructs: map[string]*rule.Construct{}}
				census[file.Language] = held
			}
			held.saw(source, stream.Header.Bridge.Name+" "+stream.Header.Bridge.Version)
			for _, node := range file.Nodes() {
				held.count(node, file.Path)
			}
		}
	}

	for language, held := range census {
		if err := held.written(language); err != nil {
			return err
		}
	}

	return nil
}

// read is the stream a source holds, through the contract's own reader so a stream that breaks it is refused here
// rather than read loosely.
func read(source string) (*contract.Stream, error) {
	handle, err := os.Open(source)
	if err != nil {
		return nil, err
	}
	defer handle.Close()

	var reader io.Reader = handle
	if strings.HasSuffix(source, ".gz") {
		unpacked, err := gzip.NewReader(handle)
		if err != nil {
			return nil, err
		}
		defer unpacked.Close()
		reader = unpacked
	}

	return contract.ReadAll(reader)
}

// counted is one language's census as it is gathered: a row per kind, and what it was read from.
type counted struct {
	constructs map[string]*rule.Construct
	sources    []string
	bridges    []string
}

// saw records that the language was read from that source, written by that bridge.
func (c *counted) saw(source, bridge string) {
	for _, held := range []struct {
		into  *[]string
		value string
	}{{&c.sources, source}, {&c.bridges, bridge}} {
		if !slices.Contains(*held.into, held.value) {
			*held.into = append(*held.into, held.value)
		}
	}
}

// count adds what the node shows about its kind: its role and neutral kinds, the slot it fills in its parent, and
// the operator, modifiers and flags it carries.
func (c *counted) count(node *contract.Node, path string) {
	construct, seen := c.constructs[node.Kind]
	if !seen {
		construct = &rule.Construct{
			Kind:    node.Kind,
			Spells:  rule.Spelling(node.Kind),
			Vouched: []string{"observed"},
			Example: fmt.Sprintf("%s:%d", path, node.Span.Line),
		}
		c.constructs[node.Kind] = construct
	}

	add(&construct.Roles, node.Role)
	add(&construct.Is, node.Is...)
	if node.Facts != nil {
		add(&construct.Operators, node.Operator)
		add(&construct.Modifiers, node.Modifiers...)
		add(&construct.Flags, node.Flags...)
	}

	// The field is the slot a node fills in its PARENT, so it is recorded on the parent's row: that is where a
	// rule asks for it, as `{"of": "child:<field>"}` and `{"count": {"child": …, "field": …}}` do.
	for _, child := range node.Children {
		add(&construct.Fields, child.Field)
	}
}

// add keeps each value once, and keeps no empty one: a node that carries no operator says nothing about operators.
func add(into *[]string, values ...string) {
	for _, value := range values {
		if value != "" && !slices.Contains(*into, value) {
			*into = append(*into, value)
		}
	}
}

// written writes the language's vocabulary, every list sorted so the file is the same from one run to the next.
func (c *counted) written(language contract.Language) error {
	vocabulary := rule.Vocabulary{Language: language, Bridges: c.bridges, Sources: c.sources}
	for _, construct := range c.constructs {
		for _, list := range []*[]string{&construct.Roles, &construct.Is, &construct.Fields, &construct.Operators, &construct.Modifiers, &construct.Flags} {
			sort.Strings(*list)
		}
		vocabulary.Constructs = append(vocabulary.Constructs, *construct)
	}

	sort.Strings(vocabulary.Bridges)
	sort.Strings(vocabulary.Sources)
	sort.Slice(vocabulary.Constructs, func(a, b int) bool {
		return vocabulary.Constructs[a].Kind < vocabulary.Constructs[b].Kind
	})

	// A spelling two kinds share would mean two constructs to a rule writing it, and nobody could say which. Both
	// then lose their spelling and are named as collided: a rule writes those two as the language spells them,
	// which is always valid. Choosing a winner, or failing until a human curates an exception, are the two things
	// a generated table exists to avoid.
	vocabulary.Collided = vocabulary.Collisions()
	for at, construct := range vocabulary.Constructs {
		if _, collided := vocabulary.Collided[construct.Spells]; collided {
			vocabulary.Constructs[at].Spells = ""
		}
	}

	written, err := json.MarshalIndent(vocabulary, "", "    ")
	if err != nil {
		return err
	}

	return os.WriteFile(filepath.Join("rule", rule.VocabularyFolder, string(language)+".json"), append(written, '\n'), 0o644)
}
