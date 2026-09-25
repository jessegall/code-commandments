// Package scaffold is `scaffold`: generate the reusable helper a sin's fix uses into the project, its
// namespace filled, skipping any that is already there.
package scaffold

import (
	"bytes"
	"encoding/json"
	"os"
	"path/filepath"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/sins"
)

// frontendRoot is where a frontend helper goes.
const frontendRoot = "resources/js"

// Command is `scaffold`.
type Command struct{}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"scaffold"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("Generate the reusable helper a sin's fix uses — written into your source root with its namespace injected. Idempotent: an existing file is skipped.").
		Form("scaffold", "generate every sin's scaffold that is missing").
		Form("scaffold --sin=NAME", "generate one sin's scaffold (lenient name match)").
		Option("--sin=NAME", "only scaffold for this sin").
		Option("--dry-run", "print what would be created, and its contents, without writing")
}

// Scaffoldable maps each sin that scaffolds a helper to the command that does it.
func Scaffoldable() map[string]string {
	commands := map[string]string{}

	for _, sin := range sins.All() {
		if scaffolding, scaffolds := sin.(sins.Scaffolding); scaffolds && len(scaffolding.Scaffolds()) > 0 {
			name := sin.Definition().Name
			commands[name] = "vendor/bin/commandments scaffold --sin=" + name
		}
	}

	return commands
}

// Run writes every missing helper the named sins scaffold.
func (Command) Run(in *cli.Input, console cli.Console) (int, error) {
	query, filtered := in.Option("sin")
	dryRun := in.HasFlag("dry-run")
	cwd, err := os.Getwd()
	if err != nil {
		return 0, err
	}

	dir, namespace, found := sourceRoot(cwd)
	if !found {
		console.Warn("Could not resolve a PSR-4 source root from composer.json (run from the project root).")

		return 2, nil
	}

	var created, skipped []string

	for _, sin := range config.InClassOrder(config.Sin, sins.All()) {
		scaffolding, scaffolds := sin.(sins.Scaffolding)

		if !scaffolds || filtered && !sin.Definition().Matches(query) {
			continue
		}

		for _, helper := range scaffolding.Scaffolds() {
			target := dir + "/" + helper.Path
			code := render(helper, namespaceFor(namespace, helper))

			if helper.Frontend {
				target = cwd + "/" + frontendRoot + "/" + helper.Path
				code = render(helper, "")
			}

			if existing, exists := existingOf(cwd, target, helper); exists {
				skipped = append(skipped, existing)

				continue
			}

			if dryRun {
				console.Write("\033[2m↳ would create " + target + "\033[0m\n" + code + "\n")

				continue
			}

			if err := os.MkdirAll(filepath.Dir(target), 0o755); err != nil {
				return 0, err
			}

			if err := os.WriteFile(target, []byte(code), 0o644); err != nil {
				return 0, err
			}

			created = append(created, target)
		}
	}

	return report(created, skipped, query, filtered, dryRun, console), nil
}

func report(created, skipped []string, query string, filtered, dryRun bool, console cli.Console) int {
	if len(created) == 0 && len(skipped) == 0 {
		where := "No sin"
		if filtered {
			where = "--sin=" + query
		}

		return console.Say("\033[2m" + where + " provides a scaffold (most fixes are domain-specific).\033[0m")
	}

	if dryRun {
		return 0
	}

	if len(created) > 0 {
		files := "files"
		if len(created) == 1 {
			files = "file"
		}

		console.Say("\033[32m✓ Scaffolded " + strconv.Itoa(len(created)) + " " + files + ".\033[0m")

		for _, file := range created {
			console.Say("  " + file)
		}
	}

	for _, file := range skipped {
		console.Say("\033[2m↳ exists, skipped " + file + "\033[0m")
	}

	return 0
}

// existingOf is the helper already in the project: the target itself, or for a frontend helper a file of
// its name anywhere under the frontend root.
func existingOf(cwd, target string, helper sins.Scaffold) (string, bool) {
	if info, err := os.Stat(target); err == nil && info.Mode().IsRegular() {
		return target, true
	}

	if !helper.Frontend {
		return "", false
	}

	return find(cwd+"/"+frontendRoot, filepath.Base(helper.Path))
}

// find is the first file named name under dir, in the order its folders list their entries.
func find(dir, name string) (string, bool) {
	folder, err := os.Open(dir)
	if err != nil {
		return "", false
	}

	names, _ := folder.Readdirnames(-1)
	folder.Close()

	for _, entry := range names {
		path := dir + "/" + entry
		info, err := os.Stat(path)

		switch {
		case err != nil:
		case info.IsDir():
			if found, exists := find(path, name); exists {
				return found, true
			}
		case entry == name:
			return path, true
		}
	}

	return "", false
}

func render(helper sins.Scaffold, namespace string) string {
	stub, _ := os.ReadFile(cli.PackageRoot() + "/stubs/" + helper.Stub)

	return strings.ReplaceAll(string(stub), "{namespace}", namespace)
}

func namespaceFor(root string, helper sins.Scaffold) string {
	sub := filepath.Dir(helper.Path)

	if sub == "." {
		return root
	}

	return root + `\` + strings.ReplaceAll(sub, "/", `\`)
}

// sourceRoot is the folder and namespace helpers go under: composer.json's PSR-4 entry for app/, else its
// first.
func sourceRoot(cwd string) (dir, namespace string, found bool) {
	raw, err := os.ReadFile(cwd + "/composer.json")
	if err != nil {
		return "", "", false
	}

	entries := psr4(raw)

	for _, entry := range entries {
		if strings.TrimRight(entry[1], "/") == "app" {
			return "app", strings.TrimRight(entry[0], `\`), true
		}
	}

	if len(entries) == 0 {
		return "", "", false
	}

	return strings.TrimRight(entries[0][1], "/"), strings.TrimRight(entries[0][0], `\`), true
}

// psr4 are composer.json's PSR-4 namespaces and folders, in the order the file writes them.
func psr4(raw []byte) [][2]string {
	var manifest struct {
		Autoload struct {
			PSR4 json.RawMessage `json:"psr-4"`
		} `json:"autoload"`
	}

	if json.Unmarshal(raw, &manifest) != nil || len(manifest.Autoload.PSR4) == 0 {
		return nil
	}

	decoder := json.NewDecoder(bytes.NewReader(manifest.Autoload.PSR4))

	if token, err := decoder.Token(); err != nil || token != json.Delim('{') {
		return nil
	}

	var entries [][2]string

	for decoder.More() {
		key, err := decoder.Token()
		if err != nil {
			return entries
		}

		var value any
		decoder.Decode(&value)

		dir := ""
		if text, isText := value.(string); isText {
			dir = text
		}

		entries = append(entries, [2]string{key.(string), dir})
	}

	return entries
}
