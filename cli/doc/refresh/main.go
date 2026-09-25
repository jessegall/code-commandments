// Command refresh projects the commands' help into the documents that describe them: the README's command
// table and every `commands:` block a skill embeds. Run it from the repository root:
// `go run ./cli/doc/refresh`.
package main

import (
	"fmt"
	"os"

	"github.com/jessegall/code-commandments/cli/block"
	"github.com/jessegall/code-commandments/cli/commands"
	"github.com/jessegall/code-commandments/cli/doc"
)

func main() {
	if err := refresh(); err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
}

func refresh() error {
	kernel := commands.Kernel("dev")

	readme, err := os.ReadFile("README.md")
	if err != nil {
		return err
	}

	table, _, err := block.Replace(string(readme), "commands-table", "\n"+doc.Overview(kernel))
	if err != nil {
		return err
	}

	if err := os.WriteFile("README.md", []byte(table), 0o644); err != nil {
		return err
	}

	documents, err := doc.DocumentsIn("skills")
	if err != nil {
		return err
	}

	for _, path := range documents {
		text, err := os.ReadFile(path)
		if err != nil {
			return err
		}

		refreshed, err := doc.Refresh(string(text), kernel)
		if err != nil {
			return fmt.Errorf("%s: %w", path, err)
		}

		if refreshed != string(text) {
			if err := os.WriteFile(path, []byte(refreshed), 0o644); err != nil {
				return err
			}
		}
	}

	return nil
}
