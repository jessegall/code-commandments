package help

import (
	"fmt"
	"io"
	"strings"

	"github.com/jessegall/code-commandments/cli/layout"
)

const (
	// gutter is where the description column starts on a two-column line.
	gutter = 2

	// width is the classic terminal width lines wrap at, so the screen reads the same everywhere.
	width = 100

	// column is the widest the label column may grow; a longer label takes its own line instead.
	column = 34
)

// globalOptions are the flags the tool answers to whatever the verb, stated once, here.
var globalOptions = []Option{
	{"--help, -h", "show this help — or, after a verb, that command's own usage"},
	{"--memory=LIMIT", "memory ceiling for the run (default: 2G; -1 for no limit)"},
	{"--version", "print the installed version of code-commandments"},
}

// Screen renders the help, the only place help text is laid out. It knows no verb by name.
type Screen struct {
	commands []Documented
}

// NewScreen lays out help for these commands, in registration order: the order the overview lists them.
func NewScreen(commands []Documented) Screen {
	return Screen{commands}
}

// Overview is the top-level screen: every command's verb and summary, grouped by the section it declares.
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

// Page is one command's page: its forms, its options, its notes.
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

// Usage is the error for a command called wrong: why, then the page --help gives, on err, with exit code 2.
// It is the only way a command fails its invocation.
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

// sections groups the commands under their declared heading, headings in first-seen order.
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

// rowsOf lays out `label   description` lines: the labels share one column, a label too long for it takes
// the next line for its description, and descriptions wrap under their own hanging indent.
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

// paragraph wraps text to the given width, every line carrying indent.
func paragraph(text string, wide int, indent string) string {
	return indent + layout.Wrap(layout.Trim(text), max(20, wide), "\n"+indent)
}
