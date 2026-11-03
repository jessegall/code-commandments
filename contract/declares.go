package contract

import (
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"slices"
)

// Declaration is what a language's parser CAN emit, as its bridge declares it, rather than what a recorded stream
// was seen carrying. A census of streams says only what some fixture happened to contain, so a kind no fixture
// holds reads as a kind the language has not got; a parser answers for its whole vocabulary.
//
// It is the answer to the one ask a bridge takes besides a tree: given `--declares` and nothing else, a bridge
// writes this as a single JSON object and exits.
type Declaration struct {
	// Language is the language the declaration is of.
	Language Language `json:"language"`
	// Parser names what answered, as the bridge's own header names it: `php-parser 5.6.1`, `python 3.13 ast`.
	Parser string `json:"parser"`
	// From says in plain words WHERE the declaration was read, because the answer is only worth what its source
	// is: php-parser's own node classes is one thing, and a bridge's own writer tables are another.
	From string `json:"from"`
	// Kinds are the kinds the parser can emit, each with the slots its children fill where the parser can say
	// which kind holds which.
	Kinds []DeclaredKind `json:"kinds"`
	// Fields are the slots a child fills anywhere in the language, for a parser that cannot say which kind holds
	// which: C# names a kind per syntax KIND and its fields per syntax CLASS, and one class answers many kinds,
	// so the two cannot be joined without inventing the join. A flat list is the honest answer, and `From` says
	// so. Empty for a parser whose kinds carry their own fields.
	Fields []string `json:"fields,omitempty"`
}

// DeclaredKind is one kind a parser can emit, and the slots a child of it fills.
type DeclaredKind struct {
	Kind   string   `json:"kind"`
	Fields []string `json:"fields,omitempty"`
}

// ReadDeclarations are the declarations written on reader, one per language the bridge writes: a bridge of one
// language answers one, and the frontend bridge, which writes TypeScript and Vue, answers both. An answer that is
// no declaration is refused rather than read loosely, as a stream is.
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

// valid says why the declaration cannot be read, and nothing where it can.
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

// Declares says whether the parser declares that kind.
func (d Declaration) Declares(kind string) bool {
	return slices.ContainsFunc(d.Kinds, func(declared DeclaredKind) bool { return declared.Kind == kind })
}

// FieldsOf are the slots a child of that kind fills, as the parser declares them; none for a kind it does not
// declare, which a reader tells from Declares rather than from an empty list.
func (d Declaration) FieldsOf(kind string) []string {
	for _, declared := range d.Kinds {
		if declared.Kind == kind {
			return declared.Fields
		}
	}

	return nil
}

// DeclaredFields are every slot a child fills in the language, whether the parser said which kind holds it or
// only that the language has it.
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
