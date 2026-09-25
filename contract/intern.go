package contract

import (
	"fmt"
	"strconv"
	"strings"
)

// interned holds one copy of every value a stream repeats: its strings, its lists of strings, its types and its
// call targets. A whole solution names the same kinds, symbols and types millions of times, and a tree that holds
// each once is several times smaller. Nothing interned is written after it is read.
type interned struct {
	strings map[string]string
	lists   map[string][]string
	types   map[string]*Type
	targets map[string]*Target
}

func newInterned() *interned {
	return &interned{strings: map[string]string{}, lists: map[string][]string{}, types: map[string]*Type{}, targets: map[string]*Target{}}
}

// file interns every value the file's nodes and comments hold.
func (in *interned) file(file *File) {
	file.Path = in.string(file.Path)
	file.Module = in.string(file.Module)
	for _, node := range file.nodes {
		in.node(node)
	}
	for at := range file.Comments {
		file.Comments[at].Kind = in.string(file.Comments[at].Kind)
	}
}

func (in *interned) node(node *Node) {
	node.Kind = in.string(node.Kind)
	node.Role = in.string(node.Role)
	node.Field = in.string(node.Field)
	node.Name = in.string(node.Name)
	node.Is = in.list(node.Is)
	node.Resolved = in.typed(node.Resolved)
	if node.Facts == none {
		return
	}
	facts := node.Facts
	facts.Literal = in.string(facts.Literal)
	facts.Operator = in.string(facts.Operator)
	facts.Modifiers = in.list(facts.Modifiers)
	facts.Flags = in.list(facts.Flags)
	facts.Declared = in.typed(facts.Declared)
	facts.Returns = in.typed(facts.Returns)
	facts.Symbol = in.string(facts.Symbol)
	facts.Refers = in.string(facts.Refers)
	facts.Target = in.target(facts.Target)
	facts.Resolves = in.string(facts.Resolves)
}

func (in *interned) string(text string) string {
	if text == "" {
		return ""
	}
	if held, ok := in.strings[text]; ok {
		return held
	}
	in.strings[text] = text

	return text
}

func (in *interned) list(texts []string) []string {
	if len(texts) == 0 {
		return texts
	}
	key := strings.Join(texts, "\x00")
	if held, ok := in.lists[key]; ok {
		return held
	}
	for at, text := range texts {
		texts[at] = in.string(text)
	}
	texts = texts[:len(texts):len(texts)]
	in.lists[key] = texts

	return texts
}

// typed is the one type equal to this one, its parts interned first so equal types are made of the same parts.
func (in *interned) typed(written *Type) *Type {
	if written == nil {
		return nil
	}
	written.Text = in.string(written.Text)
	written.Kind = in.string(written.Kind)
	written.Name = in.string(written.Name)
	written.Constructs = in.string(written.Constructs)
	written.Origin = in.string(written.Origin)
	written.Args = in.typeList(written.Args)
	written.Members = in.typeList(written.Members)
	written.Parameters = in.typeList(written.Parameters)
	written.Returns = in.typed(written.Returns)
	written.Element = in.typed(written.Element)
	for at := range written.Fields {
		written.Fields[at].Name = in.string(written.Fields[at].Name)
		written.Fields[at].Type = in.typed(written.Fields[at].Type)
	}
	key := typeKey(written)
	if held, ok := in.types[key]; ok {
		return held
	}
	in.types[key] = written

	return written
}

func (in *interned) typeList(written []*Type) []*Type {
	for at, part := range written {
		written[at] = in.typed(part)
	}

	return written
}

func (in *interned) target(target *Target) *Target {
	if target == nil {
		return nil
	}
	target.Symbol = in.string(target.Symbol)
	target.Type = in.string(target.Type)
	target.Name = in.string(target.Name)
	target.Parameters = in.list(target.Parameters)
	key := target.Symbol + "\x00" + target.Type + "\x00" + target.Name + "\x00" + strings.Join(target.Parameters, "\x01")
	if held, ok := in.targets[key]; ok {
		return held
	}
	in.targets[key] = target

	return target
}

// typeKey names a type by its own fields and the addresses of its interned parts: two types with the same key are
// equal in every field.
func typeKey(t *Type) string {
	var key strings.Builder
	for _, text := range []string{t.Text, t.Kind, t.Name, t.Constructs, t.Origin} {
		key.WriteString(text)
		key.WriteByte(0)
	}
	key.WriteString(strconv.FormatBool(t.Nullable) + strconv.FormatBool(t.ValueType))
	if t.Value != nil {
		key.WriteString("=" + string(t.Value.raw))
	}
	for _, parts := range [][]*Type{t.Args, t.Members, t.Parameters, {t.Returns, t.Element}} {
		key.WriteByte('|')
		for _, part := range parts {
			fmt.Fprintf(&key, "%p,", part)
		}
	}
	for _, field := range t.Fields {
		fmt.Fprintf(&key, "|%s:%p:%t", field.Name, field.Type, field.Optional)
	}

	return key.String()
}
