package contract

import (
	"encoding/json"
	"io"
	"slices"
)

func Write(writer io.Writer, stream *Stream) error {
	encoder := json.NewEncoder(writer)

	if err := encoder.Encode(Line{Header: &stream.Header}); err != nil {
		return err
	}

	for _, file := range stream.Files {
		if err := encoder.Encode(Line{File: file.onTheWire(stream.Header.Language)}); err != nil {
			return err
		}
	}

	if stream.Program != nil {
		if err := encoder.Encode(Line{Program: stream.Program}); err != nil {
			return err
		}
	}

	trailer := stream.Trailer
	trailer.Files = len(stream.Files)

	return encoder.Encode(Line{Trailer: &trailer})
}

func (f *File) onTheWire(language Language) *File {
	wire := *f
	named := map[*Type]int{}

	wire.Root = nodeOnTheWire(f.Root, engineNodeFacts[language], named, &wire.Types)

	if language != CSharp {
		wire.Comments = commentsOnTheWire(f.Comments)
	}

	return &wire
}

func commentsOnTheWire(comments []Comment) []Comment {
	wire := slices.Clone(comments)

	for at := range wire {
		wire[at].Refs = slices.Clone(wire[at].Refs)

		for ref := range wire[at].Refs {
			wire[at].Refs[ref] = Ref{Text: wire[at].Refs[ref].Text}
		}
	}

	return wire
}

func nodeOnTheWire(node *Node, enginesFacts []string, named map[*Type]int, types *[]*Type) *Node {
	if node == nil {
		return nil
	}

	wire := *node
	wire.parent = nil

	wire.forgets(enginesFacts)

	if wire.Resolved != nil {
		index, seen := named[wire.Resolved]
		if !seen {
			index = len(*types)
			named[wire.Resolved] = index
			*types = append(*types, wire.Resolved)
		}

		wire.Resolved, wire.ResolvedType = nil, &index
	}

	if len(node.Children) > 0 {
		wire.Children = make([]*Node, len(node.Children))
		for position, child := range node.Children {
			wire.Children[position] = nodeOnTheWire(child, enginesFacts, named, types)
		}
	}

	return &wire
}
