package contract

import (
	"encoding/json"
	"io"
)

func Write(writer io.Writer, stream *Stream) error {
	encoder := json.NewEncoder(writer)

	if err := encoder.Encode(Line{Header: &stream.Header}); err != nil {
		return err
	}

	for _, file := range stream.Files {
		if err := encoder.Encode(Line{File: file.onTheWire()}); err != nil {
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

func Encoded(line Line) ([]byte, error) {
	if line.File != nil {
		line.File = line.File.onTheWire()
	}

	return json.Marshal(line)
}

func (f *File) onTheWire() *File {
	wire := *f
	named := map[*Type]int{}
	wire.Root = nodeOnTheWire(f.Root, named, &wire.Types)

	return &wire
}

func nodeOnTheWire(node *Node, named map[*Type]int, types *[]*Type) *Node {
	if node == nil {
		return nil
	}
	wire := *node
	wire.parent, wire.role = nil, ""
	if wire.Resolved != nil {
		index, seen := named[wire.Resolved]
		if !seen {
			index = len(*types)
			named[wire.Resolved] = index
			*types = append(*types, wire.Resolved)
		}
		facts := node.Facts.copied()
		facts.ResolvedType = &index
		wire.Facts = facts
		wire.Resolved = nil
	}
	if wire.Facts == none {
		wire.Facts = nil
	}
	wire.Parts = make(Parts, len(node.Parts))
	for at, part := range node.Parts {
		wire.Parts[at] = Part{Role: part.Role, List: part.List, Nodes: make([]*Node, len(part.Nodes))}
		for index, child := range part.Nodes {
			wire.Parts[at].Nodes[index] = nodeOnTheWire(child, named, types)
		}
	}
	if len(wire.Parts) == 0 {
		wire.Parts = nil
	}

	return &wire
}
