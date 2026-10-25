package contract

import (
	"encoding/json"
	"fmt"
)

// Accept is the stream a bridge in this very process built, held to everything a read holds a stream to: the
// version, the order of the lines, each file's nodes numbered and given their parents, the types a file says
// once put back on the nodes that name them, and no fact the engine fills carried in the stream.
//
// It exists for the one bridge that needs no process. Writing a tree as JSON only to parse it straight back
// costs most of the time such a bridge takes — measured over this repository, 18 seconds of which the typing is
// under one — and the whole of that is encoding and decoding. What it must NOT cost is the checking: a stream
// built in memory goes through the same acceptance as a stream read from a pipe, which is why this is here and
// not a second path of its own.
func Accept(header Header, files []*File, program *Program, trailer Trailer) (*Stream, error) {
	reader := &Reader{}
	if err := reader.accept(Line{Header: &header}); err != nil {
		return nil, err
	}
	for _, file := range files {
		if err := reader.accept(Line{File: file}); err != nil {
			return nil, err
		}
	}
	if program != nil {
		if err := reader.accept(Line{Program: program}); err != nil {
			return nil, err
		}
	}
	if err := reader.accept(Line{Trailer: &trailer}); err != nil {
		return nil, err
	}

	return &Stream{Header: header, Files: files, Program: program, Trailer: trailer}, nil
}

// ValueOf is the value a literal decodes to, as a node carries it: a string without its quotes, a boolean, or
// null. A number is a decimal string, so no width is lost in reading it back.
func ValueOf(value any) (*Value, error) {
	switch value.(type) {
	case nil, string, bool:
	default:
		return nil, fmt.Errorf("a value is a string, a boolean or null, got %T", value)
	}

	raw, err := json.Marshal(value)
	if err != nil {
		return nil, err
	}

	return &Value{raw: raw}, nil
}
