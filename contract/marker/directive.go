package marker

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"regexp"
	"strconv"
	"strings"
)

// Directive is one marker a sample source line carries: the contract item that line must produce, or a label naming a
// node for other markers to point at.
type Directive struct {
	Label    string
	Key      string
	Owner    string
	Column   int
	Operator string
	Targets  []Target
	Value    json.RawMessage
}

// Target is the node a role or link marker points at: a label of the same file, a line and kind, or a label in
// another source.
type Target struct {
	Label  string
	Path   string
	Line   int
	Kind   string
	Column int
}

var (
	identifier = regexp.MustCompile(`^[A-Za-z_][A-Za-z0-9_]*$`)
	lineTarget = regexp.MustCompile(`^line\((\d+),([a-z-]+)(?:,(\d+))?\)$`)
)

func Parse(text string) (Directive, error) {
	text = strings.TrimSpace(text)
	switch {
	case strings.HasPrefix(text, "@label "):
		return label(strings.TrimSpace(strings.TrimPrefix(text, "@label ")))
	case strings.HasPrefix(text, "@contract"):
		return item(strings.TrimSpace(strings.TrimPrefix(text, "@contract")))
	}

	return Directive{}, fmt.Errorf("%q is neither an @contract nor an @label marker", text)
}

func label(rest string) (Directive, error) {
	fields := strings.Fields(rest)
	if len(fields) < 2 || len(fields) > 3 {
		return Directive{}, fmt.Errorf("@label takes a name, a kind and an optional column, not %q", rest)
	}
	if !identifier.MatchString(fields[0]) {
		return Directive{}, fmt.Errorf("@label name %q is not an ASCII identifier", fields[0])
	}
	made := Directive{Label: fields[0], Owner: fields[1]}
	if len(fields) == 3 {
		column, err := columnOf(fields[2])
		if err != nil {
			return Directive{}, err
		}
		made.Column = column
	}

	return made, nil
}

func item(rest string) (Directive, error) {
	key, rest, _ := strings.Cut(rest, " ")
	if key == "" {
		return Directive{}, errors.New("@contract names no item key")
	}
	made := Directive{Key: key}
	rest = strings.TrimSpace(rest)
	if word, after, found := strings.Cut(rest, " "); found && word == "on" {
		made.Owner, rest = firstWord(after)
	}
	if word, after, found := strings.Cut(rest, " "); found && word == "at" {
		written, remaining := firstWord(after)
		column, err := columnOf(written)
		if err != nil {
			return Directive{}, err
		}
		made.Column, rest = column, remaining
	}
	switch {
	case rest == "":
		return made, nil
	case strings.HasPrefix(rest, "->"):
		targets, err := targetsOf(strings.TrimSpace(strings.TrimPrefix(rest, "->")))
		made.Operator, made.Targets = "->", targets

		return made, err
	case strings.HasPrefix(rest, "="):
		value, err := valueOf(strings.TrimSpace(strings.TrimPrefix(rest, "=")))
		made.Operator, made.Value = "=", value

		return made, err
	}

	return Directive{}, fmt.Errorf("@contract %s: %q is neither an operator (-> or =) nor on or at", key, rest)
}

func firstWord(text string) (string, string) {
	word, rest, _ := strings.Cut(strings.TrimSpace(text), " ")

	return word, strings.TrimSpace(rest)
}

func columnOf(written string) (int, error) {
	column, err := strconv.Atoi(written)
	if err != nil || column < 1 {
		return 0, fmt.Errorf("column %q is not a one-based byte column", written)
	}

	return column, nil
}

func targetsOf(rest string) ([]Target, error) {
	if !strings.HasPrefix(rest, "[") {
		written, trailing := firstWord(rest)
		if trailing != "" {
			return nil, fmt.Errorf("text after the target %s: %q", written, trailing)
		}
		target, err := targetOf(written)

		return []Target{target}, err
	}
	closing := strings.Index(rest, "]")
	if closing < 0 {
		return nil, fmt.Errorf("the target list %q is not closed with ]", rest)
	}
	if trailing := strings.TrimSpace(rest[closing+1:]); trailing != "" {
		return nil, fmt.Errorf("text after the target list: %q", trailing)
	}
	listed := strings.TrimSpace(rest[1:closing])
	targets := []Target{}
	if listed == "" {
		return targets, nil
	}
	for _, written := range splitTargets(listed) {
		target, err := targetOf(strings.TrimSpace(written))
		if err != nil {
			return nil, err
		}
		targets = append(targets, target)
	}

	return targets, nil
}

func splitTargets(listed string) []string {
	var parts []string
	depth, start := 0, 0
	for at, char := range listed {
		switch {
		case char == '(':
			depth++
		case char == ')':
			depth--
		case char == ',' && depth == 0:
			parts = append(parts, listed[start:at])
			start = at + 1
		}
	}

	return append(parts, listed[start:])
}

func targetOf(written string) (Target, error) {
	if matched := lineTarget.FindStringSubmatch(written); matched != nil {
		line, _ := strconv.Atoi(matched[1])
		target := Target{Line: line, Kind: matched[2]}
		if matched[3] != "" {
			target.Column, _ = strconv.Atoi(matched[3])
		}

		return target, nil
	}
	if path, name, crosses := strings.Cut(written, "#"); crosses && path != "" && identifier.MatchString(name) {
		return Target{Path: path, Label: name}, nil
	}
	if identifier.MatchString(written) {
		return Target{Label: written}, nil
	}

	return Target{}, fmt.Errorf("%q is no target: a label, line(N,KIND[,COLUMN]) or path#label", written)
}

func valueOf(written string) (json.RawMessage, error) {
	if written == "" {
		return nil, errors.New("= takes a value")
	}
	decoder := json.NewDecoder(strings.NewReader(written))
	decoder.UseNumber()
	var value any
	if err := decoder.Decode(&value); err != nil {
		return nil, fmt.Errorf("the value %q is not complete JSON: %v", written, err)
	}
	if trailing := strings.TrimSpace(written[decoder.InputOffset():]); trailing != "" {
		return nil, fmt.Errorf("text after the value: %q", trailing)
	}

	return json.RawMessage(bytes.TrimSpace([]byte(written))), nil
}
