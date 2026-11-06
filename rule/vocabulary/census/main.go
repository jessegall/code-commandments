// Command census writes the observed vocabulary of every language: the kinds, roles, neutral kinds, fields, operators, modifiers and flags the recorded streams really carry, each with the place the census first met it.
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

	for language, declared := range declarations() {
		held, seen := census[language]
		if !seen {
			held = &counted{constructs: map[string]*rule.Construct{}}
			census[language] = held
		}
		held.declares(declared)
	}

	for language, held := range census {
		if err := held.written(language); err != nil {
			return err
		}
	}

	return nil
}

func declarations() map[contract.Language]contract.Declaration {
	found := map[contract.Language]contract.Declaration{}
	files, err := filepath.Glob(filepath.Join(rule.DeclarationsFolder, "*.json"))
	if err != nil {
		return found
	}

	for _, file := range files {
		handle, err := os.Open(file)
		if err != nil {
			continue
		}
		declared, err := contract.ReadDeclarations(handle)
		handle.Close()
		if err != nil {
			continue
		}
		for _, one := range declared {
			found[one.Language] = one
		}
	}

	return found
}

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
	constructs  map[string]*rule.Construct
	sources     []string
	bridges     []string
	parser      string
	from        string
	fields      []string
	answeredFor []string
}

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

	for _, child := range node.Children {
		add(&construct.Fields, child.Field)
	}
}

func add(into *[]string, values ...string) {
	for _, value := range values {
		if value != "" && !slices.Contains(*into, value) {
			*into = append(*into, value)
		}
	}
}

func (c *counted) declares(declared contract.Declaration) {
	c.parser = declared.Parser
	c.from = declared.From
	c.fields = declared.Fields

	c.answeredFor = []string{"kind"}
	if len(declared.DeclaredFields()) > 0 {
		c.answeredFor = append(c.answeredFor, "field")
	}

	for _, kind := range declared.Kinds {
		construct, seen := c.constructs[kind.Kind]
		if !seen {
			construct = &rule.Construct{Kind: kind.Kind, Spells: rule.Spelling(kind.Kind)}
			c.constructs[kind.Kind] = construct
		}
		add(&construct.Vouched, "declared")
		add(&construct.Fields, kind.Fields...)
	}
}

func (c *counted) written(language contract.Language) error {
	vocabulary := rule.Vocabulary{Language: language, Bridges: c.bridges, Sources: c.sources,
		Parser: c.parser, Declares: c.from, DeclaredFields: c.fields, DeclaresKeys: c.answeredFor}
	for _, construct := range c.constructs {
		for _, list := range []*[]string{&construct.Roles, &construct.Is, &construct.Fields, &construct.Operators, &construct.Modifiers, &construct.Flags, &construct.Vouched} {
			sort.Strings(*list)
		}
		vocabulary.Constructs = append(vocabulary.Constructs, *construct)
	}

	sort.Strings(vocabulary.DeclaredFields)
	sort.Strings(vocabulary.Bridges)
	sort.Strings(vocabulary.Sources)
	sort.Slice(vocabulary.Constructs, func(a, b int) bool {
		return vocabulary.Constructs[a].Kind < vocabulary.Constructs[b].Kind
	})

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
