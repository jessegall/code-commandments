package hooks

import "github.com/jessegall/code-commandments/walk"

// Told is the checkout the journal names on its own line, which a plugin is never to work out for itself: where it is, the project it belongs to, the branch it is on, the commit it was cut from, and whether it is still there.
type Told struct {
	Root    string
	Project string
	Branch  string
	Base    string
	Exists  bool
	Given   bool
}

func toldOn(line, data map[string]any) Told {
	if _, named := line["checkout"].(map[string]any); named {
		return toldIn(line)
	}

	return toldIn(data)
}

func toldIn(data map[string]any) Told {
	block, named := data["checkout"].(map[string]any)
	if !named {
		return Told{}
	}

	exists, said := block["exists"].(bool)

	return Told{
		Root:    asText(block["root"]),
		Project: asText(block["project_root"]),
		Branch:  asText(block["branch"]),
		Base:    asText(firstOf(block["base_commit"], block["base"])),
		Exists:  exists || !said,
		Given:   true,
	}
}

func (t Told) rootOrDerived(derived, file string) (string, string) {
	if !t.Given || t.Root == "" {
		return derived, "git"
	}

	if _, ownsTheFile := walk.Inside(derived, file); ownsTheFile && derived != "" && !walk.Same(derived, t.Root) {
		return derived, "git, since the file lies in a checkout of its own, not the one the line names"
	}

	return t.Root, "the line"
}
