package contract

import (
	"fmt"
	"slices"
)

var engineNodeFacts = map[Language][]string{
	PHP:        {"resolved", "target", "resolves", "constant", "inherited"},
	Python:     {"refers", "target", "resolves", "constant", "inherited"},
	CSharp:     {"resolves"},
	TypeScript: {"constant", "inherited"},
	Vue:        {"constant", "inherited"},
	Go:         {},
}

var bridgeProgramFacts = map[Language][]string{
	PHP:        {"symbols"},
	Python:     {"packages"},
	CSharp:     {"symbols"},
	TypeScript: {"aliases"},
	Vue:        {"aliases"},
	Go:         {},
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

func (n *Node) forgets(facts []string) {
	if !slices.ContainsFunc(facts, n.carries) {
		return
	}

	own := *n.Facts
	n.Facts = &own

	for _, fact := range facts {
		switch fact {
		case "resolved":
			n.Resolved = nil
		case "target":
			n.Target = nil
		case "resolves":
			n.Resolves = ""
		case "constant":
			n.Constant = false
		case "inherited":
			n.Inherited = false
		case "refers":
			n.Refers = ""
		}
	}
}

func (r Ref) isResolved() bool {
	return r.Symbol != "" || r.Owner != "" || r.OwnedHere != nil || r.Blind
}

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
