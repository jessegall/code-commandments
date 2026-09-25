package contract

import (
	"fmt"
	"slices"
)

// engineNodeFacts are the node facts the engine fills for each language, so a stream never carries them.
var engineNodeFacts = map[Language][]string{
	PHP:        {"resolved", "target", "resolves", "constant", "inherited"},
	Python:     {"refers", "target", "resolves", "constant", "inherited"},
	CSharp:     {"resolves"},
	TypeScript: {"constant", "inherited"},
	Vue:        {"constant", "inherited"},
}

// bridgeProgramFacts are the program facts each language's bridge fills.
var bridgeProgramFacts = map[Language][]string{
	PHP:        {"symbols"},
	Python:     {"packages"},
	CSharp:     {"symbols"},
	TypeScript: {"aliases"},
	Vue:        {"aliases"},
}

func (n *Node) carries(fact string) bool {
	switch fact {
	case "resolved":
		return n.Resolved != nil
	case "target":
		return n.Target != nil
	case "resolves":
		return n.Resolves != ""
	case "constant":
		return n.Constant
	case "inherited":
		return n.Inherited
	case "refers":
		return n.Refers != ""
	}

	return false
}

func (r Ref) isResolved() bool {
	return r.Symbol != "" || r.Owner != "" || r.OwnedHere != nil || r.Blind
}

// fillsFile refuses a file that carries a fact the engine fills for its stream's language.
func fillsFile(language Language, file *File) error {
	if file.Test && language != CSharp {
		return fmt.Errorf("test is a fact only the C# bridge fills")
	}
	for _, node := range file.nodes {
		for _, fact := range engineNodeFacts[language] {
			if node.carries(fact) {
				return fmt.Errorf("node %d carries %s, which the engine fills for %s", node.ID, fact, language)
			}
		}
	}
	if language == CSharp {
		return nil
	}
	for _, comment := range file.Comments {
		for _, ref := range comment.Refs {
			if ref.isResolved() {
				return fmt.Errorf("comment %d resolves a reference, which the engine does for %s", comment.ID, language)
			}
		}
	}

	return nil
}

// fillsProgram refuses a program line holding a fact the language's bridge has no source for.
func fillsProgram(language Language, program *Program) error {
	carried := map[string]bool{
		"symbols":  len(program.Symbols) > 0,
		"packages": len(program.Packages) > 0,
		"aliases":  len(program.Aliases) > 0,
	}
	for fact, present := range carried {
		if present && !slices.Contains(bridgeProgramFacts[language], fact) {
			return fmt.Errorf("the program line carries %s, which %s has no source for", fact, language)
		}
	}

	return nil
}
