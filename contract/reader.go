package contract

import (
	"bufio"
	"bytes"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"slices"
)

var SupportedVersions = []int{1, 2, 3, 4}

const maxLine = 256 << 20

// Line is one line of a stream; exactly one of its fields is set.
type Line struct {
	Header  *Header  `json:"header,omitempty"`
	File    *File    `json:"file,omitempty"`
	Program *Program `json:"program,omitempty"`
	Trailer *Trailer `json:"trailer,omitempty"`
}

// Stream is a whole stream, read to its trailer.
type Stream struct {
	Header  Header
	Files   []*File
	Program *Program
	Trailer Trailer
}

func checksSchema() bool {
	return flag.Lookup("test.v") != nil
}

// Reader reads a stream line by line and refuses one that breaks the contract.
type Reader struct {
	scanner *bufio.Scanner
	number  int
	header  *Header
	files   int
	program bool
	done    bool
	held    *interned
}

func NewReader(r io.Reader) *Reader {
	scanner := bufio.NewScanner(r)
	scanner.Buffer(make([]byte, 0, 1<<20), maxLine)

	return &Reader{scanner: scanner}
}

func (r *Reader) Next() (Line, error) {
	if r.done {
		return Line{}, io.EOF
	}
	if !r.scanner.Scan() {
		if err := r.scanner.Err(); err != nil {
			return Line{}, err
		}

		return Line{}, fmt.Errorf("the stream ends after line %d without a trailer", r.number)
	}
	r.number++
	raw := r.scanner.Bytes()
	if checksSchema() {
		if err := Validate(raw); err != nil {
			return Line{}, fmt.Errorf("line %d: %w", r.number, err)
		}
	}
	line, err := decode(raw)
	if err != nil {
		return Line{}, fmt.Errorf("line %d: %w", r.number, err)
	}
	if err := r.accept(line); err != nil {
		return Line{}, fmt.Errorf("line %d: %w", r.number, err)
	}

	return line, nil
}

func ReadAll(r io.Reader) (*Stream, error) {
	return NewReader(r).Stream()
}

func (r *Reader) Stream() (*Stream, error) {
	return r.Tallied(func() {})
}

func (r *Reader) Tallied(tally func()) (*Stream, error) {
	stream := &Stream{}
	err := r.Each(func(line Line, _ []byte) error {
		switch {
		case line.Header != nil:
			stream.Header = *line.Header
		case line.File != nil:
			stream.Files = append(stream.Files, line.File)
			if !line.File.Context {
				tally()
			}
		case line.Program != nil:
			stream.Program = line.Program
		case line.Trailer != nil:
			stream.Trailer = *line.Trailer
		}

		return nil
	})
	if err != nil && stream.Header.Contract == "" {
		return nil, err
	}

	return stream, err
}

func (r *Reader) Each(each func(line Line, raw []byte) error) error {
	*r = Reader{scanner: r.scanner, number: r.number}
	for {
		line, err := r.Next()
		if err == io.EOF {
			return nil
		}
		if err != nil {
			return err
		}
		if err := each(line, r.scanner.Bytes()); err != nil {
			return err
		}
	}
}

func decode(raw []byte) (Line, error) {
	decoder := json.NewDecoder(bytes.NewReader(raw))
	decoder.DisallowUnknownFields()
	var line Line
	if err := decoder.Decode(&line); err != nil {
		return Line{}, err
	}

	return line, nil
}

func (r *Reader) accept(line Line) error {
	if line.Header != nil {
		return r.acceptHeader(line.Header)
	}
	if r.header == nil {
		return fmt.Errorf("a stream opens with its header")
	}
	switch {
	case line.File != nil:
		return r.acceptFile(line.File)
	case line.Program != nil:
		return r.acceptProgram(line.Program)
	default:
		return r.acceptTrailer(line.Trailer)
	}
}

func (r *Reader) acceptHeader(header *Header) error {
	if r.header != nil {
		return fmt.Errorf("a stream has one header")
	}
	if !slices.Contains(SupportedVersions, header.Version) {
		return fmt.Errorf("contract version %d is not one this reader reads (%v)", header.Version, SupportedVersions)
	}
	r.header = header

	return nil
}

func (r *Reader) acceptFile(file *File) error {
	if r.program {
		return fmt.Errorf("%s comes after the program line, which follows every file", file.Path)
	}
	if err := resolveTypes(file); err != nil {
		return fmt.Errorf("%s: %w", file.Path, err)
	}
	if err := link(file); err != nil {
		return fmt.Errorf("%s: %w", file.Path, err)
	}
	if err := fillsFile(r.header.Language, file); err != nil {
		return fmt.Errorf("%s: %w", file.Path, err)
	}
	if r.held == nil {
		r.held = newInterned()
	}
	r.held.file(file)
	r.files++

	return nil
}

func (r *Reader) acceptProgram(program *Program) error {
	if r.program {
		return fmt.Errorf("a stream has at most one program line")
	}
	r.program = true

	return fillsProgram(r.header.Language, program)
}

func (r *Reader) acceptTrailer(trailer *Trailer) error {
	if trailer.Files != r.files {
		return fmt.Errorf("the trailer counts %d files, the stream holds %d", trailer.Files, r.files)
	}
	r.done = true

	return nil
}

func resolveTypes(file *File) error {
	var walk func(node *Node) error
	walk = func(node *Node) error {
		if node == nil {
			return nil
		}
		if node.ResolvedType != nil {
			if *node.ResolvedType < 0 || *node.ResolvedType >= len(file.Types) {
				return fmt.Errorf("node %d resolves to type %d, and the file names %d", node.ID, *node.ResolvedType, len(file.Types))
			}
			node.Resolved, node.ResolvedType = file.Types[*node.ResolvedType], nil
		}
		for _, child := range node.Children {
			if err := walk(child); err != nil {
				return err
			}
		}

		return nil
	}
	err := walk(file.Root)
	file.Types = nil

	return err
}

func link(file *File) error {
	file.nodes, file.reaches = nil, 0
	var walk func(node, parent *Node) error
	walk = func(node, parent *Node) error {
		if node.ID != len(file.nodes) {
			return fmt.Errorf("node %d is number %d in a pre-order walk", node.ID, len(file.nodes))
		}
		if parent != nil && node.Field == "" {
			return fmt.Errorf("node %d names no field in its parent", node.ID)
		}
		if parent == nil && node.Field != "" {
			return fmt.Errorf("the root fills no field")
		}
		if node.Span.Start > node.Span.End {
			return fmt.Errorf("node %d's span starts after it ends", node.ID)
		}
		node.parent = parent
		if node.Facts == nil {
			node.Facts = none
		}
		if node.Span.End > file.reaches {
			file.reaches = node.Span.End
		}
		file.nodes = append(file.nodes, node)
		for _, child := range node.Children {
			if err := walk(child, node); err != nil {
				return err
			}
		}

		return nil
	}
	if err := walk(file.Root, nil); err != nil {
		return err
	}
	for position, comment := range file.Comments {
		if comment.ID != position {
			return fmt.Errorf("comment %d stands at position %d", comment.ID, position)
		}
		if comment.Span.Start > comment.Span.End {
			return fmt.Errorf("comment %d's span starts after it ends", comment.ID)
		}
		if comment.Attached != nil && *comment.Attached >= len(file.nodes) {
			return fmt.Errorf("comment %d is attached to node %d, which the file does not hold", comment.ID, *comment.Attached)
		}
		if comment.Span.End > file.reaches {
			file.reaches = comment.Span.End
		}
	}

	return nil
}
