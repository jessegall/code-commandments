// Package doc projects the commands' own help into the documents that describe them: the README's command
// table and every `commands:<verbs>` block a skill embeds. Nothing here is written by hand.
package doc

import (
	"io/fs"
	"os"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/block"
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

// DocumentsIn are the paths of every Markdown document under the directory, sorted.
func DocumentsIn(directory string) ([]string, error) {
	var documents []string

	err := filepath.WalkDir(directory, func(path string, entry fs.DirEntry, err error) error {
		if err != nil {
			return err
		}

		if info, err := os.Stat(path); err == nil && info.Mode().IsRegular() && filepath.Ext(path) == ".md" {
			documents = append(documents, path)
		}

		return nil
	})

	slices.Sort(documents)

	return documents, err
}

// Refresh renders every `commands:` block the document embeds from the commands' help.
func Refresh(document string, kernel *cli.Kernel) (string, error) {
	for _, name := range blockNames(document) {
		verbs := strings.Split(strings.TrimPrefix(name, prefix), ",")
		rendered := ForVerbs(kernel, verbs...)

		if len(verbs) == 1 && verbs[0] == "all" {
			rendered = Overview(kernel)
		}

		replaced, found, err := block.Replace(document, name, "\n"+rendered+"\n")
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

func table(left, right string, rows []string) string {
	return strings.Join(append([]string{"| " + left + " | " + right + " |", "|---|---|"}, rows...), "\n") + "\n"
}

func row(syntax, does string) string {
	return "| `" + cell(syntax) + "` | " + cell(does) + " |"
}

func cell(text string) string {
	return strings.ReplaceAll(strings.Trim(text, " \t\n\r\x00\x0B"), "|", `\|`)
}
