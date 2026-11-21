// Package samples holds the expected version 5 trees every bridge must write (contract/samples/testdata/expected) and the
// comparator that proves a bridge writes them.
package samples

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"reflect"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
)

// Expected is one expected tree: the construct it shows, the source it is the tree of, and the file it must read as.
type Expected struct {
	Construct string
	Source    string
	Language  contract.Language
	File      *contract.File
}

// Name is the construct and the source's own name, as a subtest names it.
func (e Expected) Name() string {
	return e.Construct + "/" + filepath.Base(e.Source)
}

// AllExpected reads every expected tree under the folder (contract/samples/testdata/expected).
func AllExpected(folder string) ([]Expected, error) {
	trees, err := filepath.Glob(filepath.Join(folder, "*", "*.jsonl"))
	if err != nil {
		return nil, err
	}
	var expected []Expected
	for _, tree := range trees {
		handle, err := os.Open(tree)
		if err != nil {
			return nil, err
		}
		stream, err := contract.ReadAll(handle)
		handle.Close()
		if err != nil {
			return nil, fmt.Errorf("%s: %w", tree, err)
		}
		file := stream.Files[0]
		expected = append(expected, Expected{
			Construct: filepath.Base(filepath.Dir(tree)),
			Source:    filepath.Join(filepath.Dir(tree), filepath.Base(file.Path)),
			Language:  stream.Header.Language,
			File:      file,
		})
	}

	return expected, nil
}

// statedOnly are the facts an expected tree may leave unstated: what the compiler, an index or a package says, and
// the parser facts that name rather than shape (a symbol's spelling, the effective modifiers, a written type's
// structure). They are compared only where the expected tree states them.
var statedOnly = []string{"symbol", "effective", "written"}

// defaults are the values a fact has when a bridge leaves it out; a fact written with its default counts as absent.
var defaults = map[string]any{
	"passing":   "value",
	"collects":  "none",
	"position":  "either",
	"spread":    "none",
	"expansion": "none",
	"evaluated": "call",
}

// Differences lists every way the actual file differs from the expected one: kind by kind, role by role, fact by
// fact and span by span, ignoring local ids. An empty list means the bridge wrote the expected tree.
func Differences(expected, actual *contract.File) []string {
	comparing := &comparison{paired: map[*contract.Node]*contract.Node{}}
	comparing.file(expected, actual)
	if expected.Root != nil && actual.Root != nil {
		comparing.node("file", expected.Root, actual.Root)
	} else if (expected.Root == nil) != (actual.Root == nil) {
		comparing.says("file: a root on one side only")
	}
	comparing.links(expected, actual)
	comparing.comments(expected, actual)

	return comparing.differences
}

type comparison struct {
	differences []string
	paired      map[*contract.Node]*contract.Node
}

func (c *comparison) says(format string, values ...any) {
	c.differences = append(c.differences, fmt.Sprintf(format, values...))
}

func (c *comparison) file(expected, actual *contract.File) {
	if expected.Errors != actual.Errors {
		c.says("file: %d parse errors expected, %d written", expected.Errors, actual.Errors)
	}
	if expected.Test != actual.Test {
		c.says("file: test %v expected, %v written", expected.Test, actual.Test)
	}
	if expected.Module != "" && expected.Module != actual.Module {
		c.says("file: module %q expected, %q written", expected.Module, actual.Module)
	}
	if ran(expected) != ran(actual) {
		c.says("file: resolver ran %v expected, %v written", ran(expected), ran(actual))
	}
}

func ran(file *contract.File) bool {
	return file.Resolver != nil && file.Resolver.Ran
}

func (c *comparison) node(at string, expected, actual *contract.Node) {
	c.paired[expected] = actual
	where := fmt.Sprintf("%s (%s, line %d)", at, expected.Kind, expected.Span.Line)
	if expected.Kind != actual.Kind {
		c.says("%s: a %s written", where, actual.Kind)

		return
	}
	if expected.Span != actual.Span {
		c.says("%s: span %v expected, %v written", where, spanOf(expected.Span), spanOf(actual.Span))
	}
	c.facts(where, expected, actual)
	c.parts(at, where, expected, actual)
}

func spanOf(span contract.Span) [3]int {
	return [3]int{span.Start, span.End, span.Line}
}

func (c *comparison) facts(where string, expected, actual *contract.Node) {
	stated, written := factsOf(expected), factsOf(actual)
	for _, name := range unionOf(stated, written) {
		want, has := stated[name]
		got, wrote := written[name]
		if !has && !wrote {
			continue
		}
		if !has && (slices.Contains(statedOnly, name) || contract.Provenance(name) != "parser") {
			continue
		}
		if !reflect.DeepEqual(want, got) {
			c.says("%s: fact %s %v expected, %v written", where, name, shown(want, has), shown(got, wrote))
		}
	}
	if expected.Resolved != nil && (actual.Resolved == nil || expected.Resolved.Text != actual.Resolved.Text || expected.Resolved.Kind != actual.Resolved.Kind) {
		c.says("%s: resolved type %q expected, %v written", where, expected.Resolved.Text, actual.Resolved)
	}
}

func shown(value any, present bool) any {
	if !present {
		return "nothing"
	}

	return value
}

func factsOf(node *contract.Node) map[string]any {
	held := map[string]any{}
	if node.Facts == nil {
		return held
	}
	raw, err := json.Marshal(node.Facts)
	if err != nil {
		return held
	}
	json.Unmarshal(raw, &held)
	delete(held, "resolvedType")
	for name, value := range defaults {
		if held[name] == value {
			delete(held, name)
		}
	}

	return held
}

func unionOf(maps ...map[string]any) []string {
	var names []string
	for _, each := range maps {
		for name := range each {
			if !slices.Contains(names, name) {
				names = append(names, name)
			}
		}
	}
	slices.Sort(names)

	return names
}

func (c *comparison) parts(at, where string, expected, actual *contract.Node) {
	written := map[string]contract.Part{}
	for _, part := range actual.Parts {
		written[part.Role] = part
	}
	for _, part := range expected.Parts {
		got, wrote := written[part.Role]
		delete(written, part.Role)
		if !wrote && len(part.Nodes) > 0 {
			c.says("%s: role %s holds %d nodes expected, none written", where, part.Role, len(part.Nodes))

			continue
		}
		if len(part.Nodes) != len(got.Nodes) {
			c.says("%s: role %s holds %d nodes expected, %d written", where, part.Role, len(part.Nodes), len(got.Nodes))

			continue
		}
		for index, child := range part.Nodes {
			c.node(fmt.Sprintf("%s.%s[%d]", at, part.Role, index), child, got.Nodes[index])
		}
	}
	for role, part := range written {
		if len(part.Nodes) > 0 {
			c.says("%s: role %s holds %d nodes written, none expected", where, role, len(part.Nodes))
		}
	}
}

// links compares every link the expected tree states, by the nodes it pairs: a link to the expected node's pair.
func (c *comparison) links(expected, actual *contract.File) {
	for from, to := range c.paired {
		for _, link := range from.Links {
			wanted := make([]int, 0, len(link.IDs))
			for _, id := range link.IDs {
				target, _ := expected.Node(id)
				if pair, found := c.paired[target]; found {
					wanted = append(wanted, pair.ID)
				}
			}
			if got := to.Linked(link.Name); !slices.Equal(wanted, got) {
				c.says("node %d (%s, line %d): link %s to %v expected, %v written", from.ID, from.Kind, from.Span.Line, link.Name, wanted, got)
			}
		}
	}
}

func (c *comparison) comments(expected, actual *contract.File) {
	if len(expected.Comments) != len(actual.Comments) {
		c.says("comments: %d expected, %d written", len(expected.Comments), len(actual.Comments))

		return
	}
	for index, want := range expected.Comments {
		got := actual.Comments[index]
		where := fmt.Sprintf("comment %d (line %d)", index, want.Span.Line)
		if want.Form != got.Form || want.Text != got.Text || want.Span != got.Span || want.Trailing != got.Trailing {
			c.says("%s: %s %q at %v expected, %s %q at %v written", where, want.Form, want.Text, spanOf(want.Span), got.Form, got.Text, spanOf(got.Span))
		}
		if want.Fragment != nil && !reflect.DeepEqual(want.Fragment, got.Fragment) {
			c.says("%s: fragment %+v expected, %+v written", where, *want.Fragment, got.Fragment)
		}
		c.attached(where, expected, want, got)
	}
}

func (c *comparison) attached(where string, expected *contract.File, want, got contract.Comment) {
	if want.Attached == nil {
		if got.Attached != nil {
			c.says("%s: attached to nothing expected, to node %d written", where, *got.Attached)
		}

		return
	}
	target, _ := expected.Node(*want.Attached)
	pair, found := c.paired[target]
	if !found || got.Attached == nil || *got.Attached != pair.ID {
		c.says("%s: attached to the %s on line %d expected", where, target.Kind, target.Span.Line)
	}
}

// Placed copies the expected tree's source where a bridge reads it: under root, in a folder named for the construct
// with its dashes as underscores, so every language's module path is a valid name.
func (e Expected) Placed(root string) (string, error) {
	source, err := os.ReadFile(e.Source)
	if err != nil {
		return "", err
	}
	folder := filepath.Join(root, strings.ReplaceAll(e.Construct, "-", "_"))
	if err := os.MkdirAll(folder, 0o755); err != nil {
		return "", err
	}
	placed := filepath.Join(folder, filepath.Base(e.Source))

	return placed, os.WriteFile(placed, source, 0o644)
}

// Merged is every expected tree of the language as one stream, its files in path order: a whole small program of the
// language for a test that needs one.
func Merged(folder string, language contract.Language) (*contract.Stream, error) {
	all, err := AllExpected(folder)
	if err != nil {
		return nil, err
	}
	var header contract.Header
	var files []*contract.File
	for _, expected := range all {
		if expected.Language != language {
			continue
		}
		header = contract.Header{Contract: "tree", Version: 5, Language: language, Bridge: contract.Bridge{Name: "expected", Version: "8.0.0"}, Roots: []string{"/expected"}}
		files = append(files, expected.File)
	}
	slices.SortFunc(files, func(a, b *contract.File) int { return strings.Compare(a.Path, b.Path) })
	if len(files) == 0 {
		return nil, fmt.Errorf("no expected tree of %s under %s", language, folder)
	}

	return contract.Accept(header, files, nil, contract.Trailer{Files: len(files)})
}
