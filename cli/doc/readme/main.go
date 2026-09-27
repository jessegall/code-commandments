// Command readme writes the generated tables: README.{sins,scribes,skills}.md, their excerpts and the hooks and
// agents tables in README.md, and the journal plugin's settings. Run it from the repository root, in the dev
// container: scripts/dev go run ./cli/doc/readme.
package main

import (
	"fmt"
	"os"
	"slices"

	"github.com/jessegall/code-commandments/cli/doc"
	_ "github.com/jessegall/code-commandments/registry"
	_ "github.com/jessegall/code-commandments/scribes/backend"
)

func main() {
	documents, err := doc.Generated(".")
	if err != nil {
		fmt.Fprintln(os.Stderr, "✗", err)
		os.Exit(1)
	}
	var written []string
	for path, content := range documents {
		if current, _ := os.ReadFile(path); string(current) == content {
			continue
		}
		if err := os.WriteFile(path, []byte(content), 0o644); err != nil {
			fmt.Fprintln(os.Stderr, "✗", err)
			os.Exit(1)
		}
		written = append(written, path)
	}
	if len(written) == 0 {
		fmt.Println("README already current.")

		return
	}
	slices.Sort(written)
	fmt.Println("README regenerated:", written)
}
