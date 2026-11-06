package contract

import (
	"encoding/json"
	"fmt"
)

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
