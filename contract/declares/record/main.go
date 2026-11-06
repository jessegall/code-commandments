// Command record asks every bridge what its parser can emit and writes each answer under contract/declares, one file per language.
package main

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/gotree"
	"github.com/jessegall/code-commandments/contract"
)

func main() {
	if err := record(); err != nil {
		fmt.Fprintln(os.Stderr, "✗", err)
		os.Exit(1)
	}
}

func record() error {
	declared := []contract.Declaration{gotree.Declares()}

	for _, asked := range []struct {
		name    string
		command func() ([]string, error)
	}{
		{"php", php},
		{"python", bridge.Mypy},
		{"frontend", frontend},
		{"csharp", func() ([]string, error) { return bridge.RoslynInDocker(nil) }},
	} {
		command, err := asked.command()
		if err != nil {
			return fmt.Errorf("the %s bridge cannot be reached: %w", asked.name, err)
		}
		answers, err := bridge.Declares(command)
		if err != nil {
			return fmt.Errorf("the %s bridge: %w", asked.name, err)
		}
		declared = append(declared, answers...)
	}

	for _, one := range declared {
		written, err := json.MarshalIndent(one, "", "    ")
		if err != nil {
			return err
		}
		path := filepath.Join("contract", "declares", string(one.Language)+".json")
		if err := os.WriteFile(path, append(written, '\n'), 0o644); err != nil {
			return err
		}
		fmt.Printf("%s: %d kinds, %d fields, from %s\n", path, len(one.Kinds), len(one.DeclaredFields()), one.Parser)
	}

	return nil
}

func php() ([]string, error) {
	folder, err := bridge.PHP.Folder()
	if err != nil {
		return nil, err
	}

	return []string{"php", filepath.Join(folder, "bridge.php")}, nil
}

func frontend() ([]string, error) {
	folder, err := bridge.Frontend.Folder()
	if err != nil {
		return nil, err
	}

	return []string{"node", filepath.Join(folder, "bridge.mjs")}, nil
}
