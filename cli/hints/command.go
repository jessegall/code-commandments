// Package hints is `hints`: keep the Spatie Data magic surface honest — rename object factories to from<Type>,
// rewrite their calls to ::from(...), and regenerate the @method from/collect docblock hints.
package hints

import (
	"os"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/scope"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/scribes"
	"github.com/jessegall/code-commandments/scribes/backend"
)

// Command is `hints`.
type Command struct{}

// Names are the verbs it answers to.
func (Command) Names() []string {
	return []string{"hints"}
}

// Help documents it.
func (Command) Help() help.Help {
	return help.Of("Auto-fix the Spatie Data magic surface — rename non-`from…` object factories to `from<Type>`, rewrite their call sites to `::from(...)`, and regenerate the `@method from(...)`/`collect(...)` docblock hints.").
		Form("hints [path]", "apply the fixes (the default — this WRITES)").
		Form("hints [path] --dry-run[=FILE]", "preview a unified diff instead, to the screen or a file").
		Adopt(scope.Options()).
		Option("--dry-run[=FILE]", "preview the rewrite as a unified diff instead of applying it").
		Note("A scoped run (--changes/--branch) is forced to docblock-only: a rename's call sites can live " +
			"outside the scope, so renaming is whole-tree only. `repent` runs this as one of its scribes; `hints` is the focused Data-only entry.")
}

// Run rewrites the Data classes under the path the arguments name, or previews it.
func (Command) Run(in *cli.Input, console cli.Console) (int, error) {
	given, named := in.FirstArgument()
	path := strings.TrimRight(given, "/")

	if !named {
		path = "."
	}

	if info, err := os.Stat(path); err != nil || !info.IsDir() {
		console.Warn("Not a directory: " + path)

		return 2, nil
	}

	cwd, err := os.Getwd()
	if err != nil {
		return 0, err
	}

	judged, err := config.Load(path)
	if err != nil {
		return 0, err
	}

	targets, err := scope.FromArgs(in.Raw(), path, workspace.At(workspace.ProjectRoot(cwd), ""), judged.Excluded)
	if scope.IsUnavailable(err) {
		console.Warn(err.Error())

		return 2, nil
	}

	codebase, err := scan.Walk([]string{path}, source.Excluded{}).Only(source.PHP).Load()
	if err != nil {
		return 0, err
	}

	rewrites, err := backend.DataHintScribe{}.Maintain(codebase, scribes.Pass{Roots: []string{path}, Scope: targets})
	if err != nil {
		return 0, err
	}

	if rewrites.Len() == 0 {
		return console.Say("\033[32m✓ Data @method hints already current — nothing to rewrite.\033[0m"), nil
	}

	if in.HasFlag("dry-run") {
		file, _ := in.Option("dry-run")

		return preview(rewrites, path, file, console)
	}

	written, err := rewrites.Apply()
	if err != nil {
		return 0, err
	}

	files := "files"
	if len(written) == 1 {
		files = "file"
	}

	console.Say("\033[32m✓ Rewrote " + strconv.Itoa(len(written)) + " " + files + ".\033[0m")

	for _, file := range written {
		console.Say("  " + strings.TrimPrefix(file, source.Real(path)+"/"))
	}

	return 0, nil
}

func preview(rewrites scribes.Rewrites, base, file string, console cli.Console) (int, error) {
	diff, err := scribes.UnifiedDiff(rewrites, source.Real(base))
	if err != nil {
		return 0, err
	}

	if file == "" {
		console.Write(diff)

		return 0, nil
	}

	if err := os.WriteFile(file, []byte(diff), 0o644); err != nil {
		return 0, err
	}

	return console.Say("\033[2m↳ dry-run diff for " + strconv.Itoa(rewrites.Len()) + " file(s) written to " + file + "\033[0m"), nil
}
