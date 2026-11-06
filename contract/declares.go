package contract

import (
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"slices"
)

// Declaration is what a language's parser CAN emit, as its bridge declares it, rather than what a recorded stream was seen carrying.
type Declaration struct {
	Language Language       `json:"language"`
	Parser   string         `json:"parser"`
	From     string         `json:"from"`
	Kinds    []DeclaredKind `json:"kinds"`
	Fields   []string       `json:"fields,omitempty"`
}

// DeclaredKind is one kind a parser can emit, and the slots a child of it fills.
type DeclaredKind struct {
	Kind   string   `json:"kind"`
	Fields []string `json:"fields,omitempty"`
}

func ReadDeclarations(reader io.Reader) ([]Declaration, error) {
	var declared []Declaration
	lines := json.NewDecoder(reader)
	for {
		var one Declaration
		err := lines.Decode(&one)
		if errors.Is(err, io.EOF) {
			break
		}
		if err != nil {
			return nil, fmt.Errorf("the declaration cannot be read: %w", err)
		}
		if err := one.valid(); err != nil {
			return nil, err
		}
		declared = append(declared, one)
	}

	if len(declared) == 0 {
		return nil, fmt.Errorf("nothing was declared")
	}

	return declared, nil
}

func (d Declaration) valid() error {
	switch {
	case d.Language == "":
		return fmt.Errorf("the declaration names no language")
	case d.Parser == "":
		return fmt.Errorf("the declaration names no parser")
	case d.From == "":
		return fmt.Errorf("the declaration does not say what it was read from")
	case len(d.Kinds) == 0:
		return fmt.Errorf("the %s declaration names no kinds", d.Language)
	}

	for _, kind := range d.Kinds {
		if kind.Kind == "" {
			return fmt.Errorf("the %s declaration holds a kind with no name", d.Language)
		}
	}

	return nil
}

func (d Declaration) Declares(kind string) bool {
	return slices.ContainsFunc(d.Kinds, func(declared DeclaredKind) bool { return declared.Kind == kind })
}

func (d Declaration) FieldsOf(kind string) []string {
	for _, declared := range d.Kinds {
		if declared.Kind == kind {
			return declared.Fields
		}
	}

	return nil
}

func (d Declaration) DeclaredFields() []string {
	fields := slices.Clone(d.Fields)
	for _, declared := range d.Kinds {
		for _, field := range declared.Fields {
			if !slices.Contains(fields, field) {
				fields = append(fields, field)
			}
		}
	}
	slices.Sort(fields)

	return fields
}
