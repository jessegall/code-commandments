package config

import (
	"fmt"
	"os"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/help"
	"github.com/jessegall/code-commandments/cli/scan"
	"github.com/jessegall/code-commandments/cli/source"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/walk"
)

const loneImplementer = "lone-implementer-interface"

// Allow records a public interface for the lone-implementer rule.
type Allow struct{}

func (Allow) Names() []string { return []string{"allow"} }

func (Allow) Help() help.Help {
	return help.Of("Publish a public interface for the lone-implementer rule.").
		Form("allow "+loneImplementer+" <path-or-symbol>", "record one interface by its fully qualified name in .commandments/config.json").
		Note("A file must declare exactly one interface. If it declares several, use the fully qualified name instead.")
}

func (command Allow) Run(in *cli.Input, console cli.Console) (int, error) {
	rule, _ := in.FirstArgument()
	given, named := in.Argument(1)
	_, extra := in.Argument(2)
	if rule != loneImplementer || !named || extra {
		return help.Usage(console.Err, command, "Name "+loneImplementer+" and one interface file or fully qualified name."), nil
	}
	cwd, err := os.Getwd()
	if err != nil {
		return 0, err
	}
	root := workspace.ProjectRoot(cwd)
	declared, err := findContract(cwd, root, given)
	if err != nil {
		return 0, err
	}
	language, _ := source.Of(declared.Source().Language())
	for _, detector := range detectors.All() {
		engine, _ := detectors.EngineOf(detector)
		if _, accepts := detector.(publicContractDetector); !accepts || !slices.Contains(language.Engines(), engine) {
			continue
		}
		changed, err := EditorIn(root).AllowContract(engine, declared.Identity())
		if err != nil {
			return 0, err
		}
		if !changed {
			return console.Say(declared.Identity() + " is already a public contract."), nil
		}
		return console.Say("Published " + declared.Identity() + " as a public contract in .commandments/config.json."), nil
	}
	return 0, fmt.Errorf("the lone-implementer rule does not judge interfaces in %s", given)
}

func findContract(cwd, root, given string) (engine.Match, error) {
	path := given
	if !filepath.IsAbs(path) {
		path = filepath.Join(cwd, path)
	}
	if walk.Exists(path) {
		if !walk.IsFile(path) || !source.InTree(root, path) || !source.Judges(path) {
			return engine.Match{}, fmt.Errorf("%s must be a source file in this project", given)
		}
		return oneContract(scan.OneFile(path), given, engine.Match.IsInterfaceType)
	}
	if source.Judges(path) {
		return engine.Match{}, fmt.Errorf("no source file at %s", given)
	}
	project, err := Load(root)
	if err != nil {
		return engine.Match{}, err
	}
	roots := project.Paths
	if len(roots) == 0 {
		roots = DetectRoots(root)
	}
	sources := scan.Walk(Absolute(root, roots), source.Under(root, append(BuiltFolders(root), project.Excluded...)))
	return oneContract(sources, given, func(declaration engine.Match) bool {
		return declaration.IsInterfaceType() && declaration.Identity() == strings.TrimPrefix(given, `\`)
	})
}

func oneContract(sources scan.Sources, given string, matches engine.Check) (engine.Match, error) {
	codebase, err := sources.Unfilled()
	if err != nil {
		return engine.Match{}, err
	}
	found := codebase.WhereTypeDeclaration().Where(matches).Get()
	switch len(found) {
	case 0:
		return engine.Match{}, fmt.Errorf("no interface found for %s", given)
	case 1:
		return found[0], nil
	}
	var names []string
	for _, declaration := range found {
		names = append(names, declaration.Identity())
	}
	return engine.Match{}, fmt.Errorf("%s matches several interface declarations: %s. Name one by its fully qualified name or its file", given, strings.Join(names, ", "))
}
