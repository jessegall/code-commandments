package cli

import (
	"slices"
	"strings"
)

// Input is the parsed command line, built once from the arguments and handed to a Command: the single place a flag is understood.
type Input struct {
	command   string
	arguments []string
	options   map[string]string
	optionAt  []string
	flags     map[string]bool
	flagAt    []string
	raw       []string
}

func FromArgs(args []string) *Input {
	if len(args) > 0 && args[0] != "" && !strings.HasPrefix(args[0], "-") {
		return InputOf(args[0], args[1:]...)
	}

	return InputOf("", args...)
}

func InputOf(command string, args ...string) *Input {
	in := &Input{command: command, options: map[string]string{}, flags: map[string]bool{}, raw: slices.Clone(args)}

	for _, token := range args {
		body, isFlag := strings.CutPrefix(token, "--")

		if !isFlag {
			in.arguments = append(in.arguments, token)

			continue
		}

		if key, value, hasValue := strings.Cut(body, "="); hasValue {
			if _, seen := in.options[key]; !seen {
				in.optionAt = append(in.optionAt, key)
			}

			in.options[key] = value

			continue
		}

		if !in.flags[body] {
			in.flagAt = append(in.flagAt, body)
		}

		in.flags[body] = true
	}

	return in
}

func (in *Input) Command() string {
	return in.command
}

func (in *Input) Arguments() []string {
	return in.arguments
}

func (in *Input) Argument(index int) (string, bool) {
	if index < len(in.arguments) {
		return in.arguments[index], true
	}

	return "", false
}

func (in *Input) FirstArgument() (string, bool) {
	return in.Argument(0)
}

func (in *Input) HasFlag(name string) bool {
	_, valued := in.options[name]

	return in.flags[name] || valued
}

func (in *Input) Given() []string {
	var given []string

	for _, name := range append(slices.Clone(in.optionAt), in.flagAt...) {
		if !slices.Contains(given, name) {
			given = append(given, name)
		}
	}

	return given
}

func (in *Input) WantsHelp() bool {
	return in.HasFlag("help") || slices.Contains(in.raw, "-h")
}

func (in *Input) Option(name string) (string, bool) {
	value, given := in.options[name]

	return value, given
}

func (in *Input) Optional(name string) (value string, valued bool, given bool) {
	if value, valued := in.options[name]; valued {
		return value, true, true
	}

	return "", false, in.flags[name]
}

func (in *Input) Repeated(name string) []string {
	prefix := "--" + name + "="
	var values []string

	for _, token := range in.raw {
		if value, found := strings.CutPrefix(token, prefix); found {
			values = append(values, value)
		}
	}

	return values
}

func (in *Input) List(name string) []string {
	value, _ := in.Option(name)

	return Commas(value)
}

func Commas(value string) []string {
	var parts []string

	for _, part := range strings.Split(value, ",") {
		if part != "" && part != "0" {
			parts = append(parts, part)
		}
	}

	return parts
}

func (in *Input) Raw() []string {
	return in.raw
}
