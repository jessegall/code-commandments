// Package doc projects the commands' own help into the documents that describe them: the README's command
// table and every `commands:<verbs>` block a skill embeds. Nothing here is written by hand.
package doc

import (
	"fmt"
	"strings"

	"github.com/jessegall/code-commandments/cli"
)

const (
	prefix = "commands:"
	open   = "<!-- BEGIN: "
)

// Overview is the table of every command: its synopsis and its summary.
func Overview(kernel *cli.Kernel) string {
	var rows []string

	for _, command := range kernel.Commands() {
		synopsis := command.Help().Synopsis()
		if synopsis == "" {
			synopsis = command.Names()[0]
		}

		rows = append(rows, row("commandments "+synopsis, command.Help().Summary))
	}

	return table("Command", "Purpose", rows)
}

// ForVerbs is the table of every form the named verbs answer to.
func ForVerbs(kernel *cli.Kernel, verbs ...string) string {
	var rows []string

	for _, verb := range verbs {
		command, known := commandNamed(kernel, verb)
		if !known {
			continue
		}

		for _, form := range command.Help().Forms {
			does := form.Does
			if does == "" {
				does = command.Help().Summary
			}

			rows = append(rows, row("commandments "+form.Syntax, does))
		}
	}

	return table("Command", "Does", rows)
}

func commandNamed(kernel *cli.Kernel, verb string) (cli.Command, bool) {
	for _, command := range kernel.Commands() {
		for _, name := range command.Names() {
			if name == verb {
				return command, true
			}
		}
	}

	return nil, false
}

// Refresh renders every `commands:` block the document embeds from the commands' help.
func Refresh(document string, kernel *cli.Kernel) (string, error) {
	for _, name := range blockNames(document) {
		verbs := strings.Split(strings.TrimPrefix(name, prefix), ",")
		rendered := ForVerbs(kernel, verbs...)

		if len(verbs) == 1 && verbs[0] == "all" {
			rendered = Overview(kernel)
		}

		replaced, found, err := Replace(document, name, "\n"+rendered+"\n")
		if err != nil {
			return "", err
		}

		if found {
			document = replaced
		}
	}

	return document, nil
}

func blockNames(document string) []string {
	var names []string

	for _, line := range strings.Split(document, "\n") {
		marker := strings.Trim(line, " \t\n\r\x00\x0B")

		if body, found := strings.CutPrefix(marker, open+prefix); found {
			name, _, _ := strings.Cut(prefix+body, " ")
			names = append(names, name)
		}
	}

	return names
}

// Malformed is a block whose markers cannot be trusted to bound it.
type Malformed struct {
	Name   string
	Reason string
}

func (e *Malformed) Error() string {
	return fmt.Sprintf("the `%s` block cannot be placed: %s. Fix the markers by hand — writing over them would risk the text between.", e.Name, e.Reason)
}

// Replace puts content between the block's BEGIN and END markers; found is false when the document holds
// neither. Two of a marker, a lone one, or an END above its BEGIN is Malformed.
func Replace(document, name, content string) (string, bool, error) {
	lines := strings.Split(document, "\n")
	begins := linesMatching(lines, func(line string) bool {
		return strings.HasPrefix(line, open+name+" ") && strings.HasSuffix(line, "-->")
	})
	ends := linesMatching(lines, func(line string) bool { return line == End(name) })

	switch {
	case len(begins) == 0 && len(ends) == 0:
		return document, false, nil
	case len(begins) > 1 || len(ends) > 1:
		return "", false, &Malformed{name, "the document carries more than one of them"}
	case len(begins) == 0:
		return "", false, &Malformed{name, "it has an END marker with no BEGIN"}
	case len(ends) == 0:
		return "", false, &Malformed{name, "it has a BEGIN marker with no END"}
	case ends[0] < begins[0]:
		return "", false, &Malformed{name, "its END marker stands above its BEGIN"}
	}

	if !strings.HasSuffix(content, "\n") {
		content += "\n"
	}

	return strings.Join(lines[:begins[0]+1], "\n") + content + strings.Join(lines[ends[0]:], "\n"), true, nil
}

// Begin is a block's opening marker, naming the command that regenerates it.
func Begin(name, command string) string {
	return open + name + " (auto-generated, run `" + command + "`) -->"
}

// End is a block's closing marker.
func End(name string) string {
	return "<!-- END: " + name + " -->"
}

func linesMatching(lines []string, matches func(string) bool) []int {
	var found []int

	for i, line := range lines {
		if matches(strings.Trim(line, " \t\n\r\x00\x0B")) {
			found = append(found, i)
		}
	}

	return found
}

func table(left, right string, rows []string) string {
	return strings.Join(append([]string{"| " + left + " | " + right + " |", "|---|---|"}, rows...), "\n") + "\n"
}

func row(syntax, does string) string {
	return "| `" + cell(syntax) + "` | " + cell(does) + " |"
}

func cell(text string) string {
	return strings.ReplaceAll(strings.Trim(text, " \t\n\r\x00\x0B"), "|", `\|`)
}
