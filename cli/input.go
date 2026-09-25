package cli

import (
	"slices"
	"strings"
)

// Input is the parsed command line, built once from the arguments and handed to a Command: the single
// place a flag is understood. Tokens after the verb are sorted into positional arguments, bare `--flag`
// switches and `--option=value` pairs; Raw keeps the untouched tail for a collaborator that owns its own
// grammar.
type Input struct {
	command   string
	arguments []string
	options   map[string]string
	optionAt  []string
	flags     map[string]bool
	flagAt    []string
	raw       []string
}

// FromArgs parses everything after the program name. A missing or flag-first first token means no verb
// was named, and the Kernel supplies the default.
func FromArgs(args []string) *Input {
	if len(args) > 0 && args[0] != "" && !strings.HasPrefix(args[0], "-") {
		return InputOf(args[0], args[1:]...)
	}

	return InputOf("", args...)
}

// InputOf builds the input for one verb from its argument tail.
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

// Command is the verb named, or empty when none was.
func (in *Input) Command() string {
	return in.command
}

// Arguments are the positionals after the verb.
func (in *Input) Arguments() []string {
	return in.arguments
}

// Argument is the positional at index, and whether the command line carried one there.
func (in *Input) Argument(index int) (string, bool) {
	if index < len(in.arguments) {
		return in.arguments[index], true
	}

	return "", false
}

// FirstArgument is the common "path or subcommand" read.
func (in *Input) FirstArgument() (string, bool) {
	return in.Argument(0)
}

// HasFlag says whether the switch is present, bare or with a value: `--dry-run` and `--dry-run=out.diff`
// both mean dry run.
func (in *Input) HasFlag(name string) bool {
	_, valued := in.options[name]

	return in.flags[name] || valued
}

// Given is every flag name the user typed, however it was spelled: what the Kernel checks against the
// flags a command declares.
func (in *Input) Given() []string {
	var given []string

	for _, name := range append(slices.Clone(in.optionAt), in.flagAt...) {
		if !slices.Contains(given, name) {
			given = append(given, name)
		}
	}

	return given
}

// WantsHelp says whether this run asks for help rather than work: `--help`, or the short `-h`.
func (in *Input) WantsHelp() bool {
	return in.HasFlag("help") || slices.Contains(in.raw, "-h")
}

// Option is the value of a `--name=value` option, and whether one was given. A bare `--name` is a flag,
// not an option.
func (in *Input) Option(name string) (string, bool) {
	value, given := in.options[name]

	return value, given
}

// Optional reads a flag whose value may be left out, like `--dry-run[=FILE]`: given says it is present,
// and valued that a value followed the `=`.
func (in *Input) Optional(name string) (value string, valued bool, given bool) {
	if value, valued := in.options[name]; valued {
		return value, true, true
	}

	return "", false, in.flags[name]
}

// Repeated is every value given for a repeatable option, in order: `--ref=a --ref=b`.
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

// List splits a comma-list option (`--exclude=a,b`), dropping the blanks; absent is the empty list.
func (in *Input) List(name string) []string {
	value, _ := in.Option(name)

	return Commas(value)
}

// Commas splits a comma-list, dropping what PHP's filter drops: the empty string and "0".
func Commas(value string) []string {
	var parts []string

	for _, part := range strings.Split(value, ",") {
		if part != "" && part != "0" {
			parts = append(parts, part)
		}
	}

	return parts
}

// Raw is every token after the verb, verbatim.
func (in *Input) Raw() []string {
	return in.raw
}
