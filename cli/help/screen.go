package help

import (
	"fmt"
	"io"
	"strings"

	"github.com/jessegall/code-commandments/cli/layout"
)

const (
	gutter = 2

	width = 100

	column = 34
)

var globalOptions = []Option{
	{"--help, -h", "show this help — or, after a verb, that command's own usage"},
	{"--memory=LIMIT", "memory ceiling for the run (default: 2G; -1 for no limit)"},
	{"--version", "print the installed version of code-commandments"},
}

// Screen renders the help, the only place help text is laid out.
type Screen struct {
	commands []Documented
}

func NewScreen(commands []Documented) Screen {
	return Screen{commands}
}

func (s Screen) Overview() string {
	var out strings.Builder
	out.WriteString("Code Commandments — a compiler for architecture.\n\nUsage:\n  commandments <command> [options]\n")

	for _, section := range s.sections() {
		var rows [][2]string

		for _, command := range section.commands {
			rows = append(rows, [2]string{command.Names()[0], command.Help().Summary})
		}

		out.WriteString("\n" + section.name + ":\n" + rowsOf(rows))
	}

	var globals [][2]string

	for _, option := range globalOptions {
		globals = append(globals, [2]string{option.Spec, option.Does})
	}

	out.WriteString("\nGlobal options:\n" + rowsOf(globals))
	out.WriteString("\nRun `commandments <command> --help` for a command's forms, options and notes.\n")

	return out.String()
}

func (s Screen) Page(command Documented) string {
	help := command.Help()
	names := command.Names()

	var out strings.Builder
	fmt.Fprintf(&out, "commandments %s — %s\n", names[0], help.Summary)

	if len(names) > 1 {
		out.WriteString("Also answers to: " + strings.Join(names[1:], ", ") + "\n")
	}

	var forms [][2]string

	for _, form := range help.Forms {
		forms = append(forms, [2]string{"commandments " + form.Syntax, form.Does})
	}

	out.WriteString("\nUsage:\n" + rowsOf(forms))

	if len(help.Options) > 0 {
		var options [][2]string

		for _, option := range help.Options {
			options = append(options, [2]string{option.Spec, option.Does})
		}

		out.WriteString("\nOptions:\n" + rowsOf(options))
	}

	for _, note := range help.Notes {
		out.WriteString("\n" + paragraph(note, width-gutter, "  ") + "\n")
	}

	return out.String()
}

func Usage(err io.Writer, command Documented, message string) int {
	if message != "" {
		fmt.Fprintf(err, "✗ %s\n\n", message)
	}

	fmt.Fprint(err, NewScreen([]Documented{command}).Page(command))

	return 2
}

type section struct {
	name     string
	commands []Documented
}

func (s Screen) sections() []section {
	var sections []section
	at := map[string]int{}

	for _, command := range s.commands {
		name := command.Help().Section

		if _, seen := at[name]; !seen {
			at[name] = len(sections)
			sections = append(sections, section{name: name})
		}

		sections[at[name]].commands = append(sections[at[name]].commands, command)
	}

	return sections
}

func rowsOf(rows [][2]string) string {
	widest := 0

	for _, row := range rows {
		widest = max(widest, layout.Width(row[0]))
	}

	col := min(column, widest+gutter)
	indent := strings.Repeat(" ", gutter+col)

	var out strings.Builder

	for _, row := range rows {
		label, description := row[0], row[1]
		wrapped := ""

		if description != "" {
			wrapped = paragraph(description, width-layout.Width(indent), indent)
		}

		fits := layout.Width(label) < col

		if fits {
			out.WriteString(layout.TrimRight("  "+layout.Pad(label, col)+layout.TrimLeft(wrapped)) + "\n")

			continue
		}

		out.WriteString(layout.TrimRight("  "+label) + "\n")

		if wrapped != "" {
			out.WriteString(layout.TrimRight(wrapped) + "\n")
		}
	}

	return out.String()
}

func paragraph(text string, wide int, indent string) string {
	return indent + layout.Wrap(layout.Trim(text), max(20, wide), "\n"+indent)
}
