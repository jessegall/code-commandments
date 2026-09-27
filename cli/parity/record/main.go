// Command record runs the tool built from this checkout over every parity case and writes what it printed as the
// case's golden. The goldens recorded before the switch are the PHP tool's, which the tool matched.
// Run it from the repository root, in the dev container: `scripts/dev go run ./cli/parity/record [case-name...]`.
package main

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"slices"

	"github.com/jessegall/code-commandments/cli/parity"
)

func main() {
	if err := record(os.Args[1:]); err != nil {
		fmt.Fprintln(os.Stderr, "✗ "+err.Error())
		os.Exit(1)
	}
}

func record(only []string) error {
	repo, err := os.Getwd()
	if err != nil {
		return err
	}

	built, err := os.MkdirTemp("", "parity-tool-")
	if err != nil {
		return err
	}
	defer os.RemoveAll(built)
	binary := filepath.Join(built, "commandments")
	build := exec.Command("go", "build", "-o", binary, "./cmd/commandments")
	build.Dir = repo
	if out, err := build.CombinedOutput(); err != nil {
		return fmt.Errorf("build: %w\n%s", err, out)
	}

	cases, err := parity.Cases(filepath.Join(repo, parity.CasesDir))
	if err != nil {
		return err
	}

	for _, c := range cases {
		if len(only) > 0 && !slices.Contains(only, c.Name) {
			continue
		}

		scratch, err := os.MkdirTemp("", "parity-")
		if err != nil {
			return err
		}

		result, err := parity.Run(c, repo, scratch, binary)
		os.RemoveAll(scratch)

		if err != nil {
			return fmt.Errorf("%s: %w", c.Name, err)
		}

		if err := os.WriteFile(filepath.Join(repo, parity.GoldenFile(c)), []byte(parity.Golden(result)), 0o644); err != nil {
			return err
		}

		fmt.Printf("recorded %s (exit %d)\n", c.Name, result.Exit)
	}

	return nil
}
