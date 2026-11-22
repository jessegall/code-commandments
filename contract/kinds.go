package contract

import (
	"encoding/json"
	"fmt"
	"slices"
	"sort"
	"strings"
	"sync"
)

// Arity is how many nodes a role or a link holds: exactly one, at most one, or an ordered list.
type Arity string

const (
	One      Arity = "1"
	Optional Arity = "?"
	Many     Arity = "[]"
)

// KindSpec is one kind of the tree as tree.schema.json declares it: its owning roles, its links, the facts of its own
// and the facts it must carry.
type KindSpec struct {
	Roles    map[string]Arity `json:"roles"`
	Links    map[string]Arity `json:"links"`
	Facts    []string         `json:"facts"`
	Required []string         `json:"required"`
}

type kindTable struct {
	Kinds map[string]KindSpec `json:"kinds"`
	Facts map[string]struct {
		Provenance string `json:"provenance"`
	} `json:"facts"`
	UniversalFacts []string            `json:"universalFacts"`
	Promises       map[string]string   `json:"promises"`
	Categories     map[string][]string `json:"categories"`
}

var loadedKinds = sync.OnceValues(func() (kindTable, error) {
	var table kindTable
	if err := json.Unmarshal(schemaSource, &table); err != nil {
		return kindTable{}, fmt.Errorf("tree.schema.json: %w", err)
	}

	return table, nil
})

func kinds() kindTable {
	table, err := loadedKinds()
	if err != nil {
		panic(err)
	}

	return table
}

// Kinds are every kind the tree has, sorted.
func Kinds() []string {
	names := make([]string, 0, len(kinds().Kinds))
	for name := range kinds().Kinds {
		names = append(names, name)
	}
	sort.Strings(names)

	return names
}

// Kind is the declaration of one kind, and whether the tree has it.
func Kind(name string) (KindSpec, bool) {
	spec, found := kinds().Kinds[name]

	return spec, found
}

// namedPart is what a kind declares by name: the roles it owns, or the non-owning links it holds. A name the kind does not
// declare is refused by name.
type namedPart struct {
	noun     string
	declares func(KindSpec) map[string]Arity
}

var (
	ownedRoles = namedPart{noun: "role", declares: func(spec KindSpec) map[string]Arity { return spec.Roles }}
	heldLinks  = namedPart{noun: "link", declares: func(spec KindSpec) map[string]Arity { return spec.Links }}
)

func (p namedPart) declared(kind, name string) (Arity, bool) {
	spec, found := Kind(kind)
	arity, declared := p.declares(spec)[name]

	return arity, found && declared
}

func (p namedPart) arity(kind, name string) (Arity, error) {
	spec, found := Kind(kind)
	if !found {
		return "", fmt.Errorf("the tree has no kind %q", kind)
	}
	arity, declared := p.declares(spec)[name]
	if !declared {
		return "", fmt.Errorf("a %s has no %s %q: its %ss are %s", kind, p.noun, name, p.noun, listed(p.declares(spec)))
	}

	return arity, nil
}

func RoleArity(kind, role string) (Arity, error) {
	return ownedRoles.arity(kind, role)
}

func LinkArity(kind, link string) (Arity, error) {
	return heldLinks.arity(kind, link)
}

func DeclaredRole(kind, role string) (Arity, bool) {
	return ownedRoles.declared(kind, role)
}

func DeclaredLink(kind, link string) (Arity, bool) {
	return heldLinks.declared(kind, link)
}

// FactApplies says whether a node of the kind may carry the fact: one of its own, or one any node may carry.
func FactApplies(kind, fact string) bool {
	spec, found := Kind(kind)

	return found && (slices.Contains(spec.Facts, fact) || slices.Contains(kinds().UniversalFacts, fact))
}

// Provenance is the class of evidence a fact comes from: parser, compiler, index or package.
func Provenance(fact string) string {
	return kinds().Facts[fact].Provenance
}

// Category is the set of kinds a category names.
func Category(name string) []string {
	return slices.Clone(kinds().Categories[name])
}

// InCategory says whether a kind belongs to a category.
func InCategory(kind, category string) bool {
	return slices.Contains(kinds().Categories[category], kind)
}

func listed(arities map[string]Arity) string {
	if len(arities) == 0 {
		return "none"
	}
	names := make([]string, 0, len(arities))
	for name := range arities {
		names = append(names, name)
	}
	sort.Strings(names)

	return strings.Join(names, ", ")
}

// checkNodes checks a file line's every node against the kinds table: its parts fill only roles its kind declares,
// with their arity, its links are declared, and it carries only the facts its kind allows, the required ones among them.
func checkNodes(instance any) error {
	line, ok := instance.(map[string]any)
	if !ok {
		return nil
	}
	file, ok := line["file"].(map[string]any)
	if !ok {
		return nil
	}

	return checkNode(file["root"], "file.root")
}

func checkNode(value any, at string) error {
	node, ok := value.(map[string]any)
	if !ok {
		return fmt.Errorf("%s: a node is an object", at)
	}
	kind, _ := node["kind"].(string)
	spec, found := Kind(kind)
	if !found {
		return fmt.Errorf("%s: the tree has no kind %q", at, kind)
	}
	for _, fact := range spec.Required {
		if _, has := node[fact]; !has {
			return fmt.Errorf("%s: a %s needs its %q", at, kind, fact)
		}
	}
	for key := range node {
		if key == "id" || key == "kind" || key == "span" || key == "parts" || key == "links" {
			continue
		}
		if !FactApplies(kind, key) {
			return fmt.Errorf("%s: a %s carries no fact %q", at, kind, key)
		}
	}
	links, _ := node["links"].(map[string]any)
	for link := range links {
		if _, err := LinkArity(kind, link); err != nil {
			return fmt.Errorf("%s: %w", at, err)
		}
	}
	parts, _ := node["parts"].(map[string]any)
	for role, arity := range spec.Roles {
		if _, has := parts[role]; !has && arity == One {
			return fmt.Errorf("%s: a %s needs its %q", at, kind, role)
		}
	}
	for role, filled := range parts {
		arity, err := RoleArity(kind, role)
		if err != nil {
			return fmt.Errorf("%s: %w", at, err)
		}
		where := at + "." + role
		list, isList := filled.([]any)
		if isList != (arity == Many) {
			return fmt.Errorf("%s: a %s's %q holds %s", where, kind, role, map[bool]string{true: "a list", false: "one node"}[arity == Many])
		}
		if !isList {
			list = []any{filled}
		}
		for index, child := range list {
			if err := checkNode(child, fmt.Sprintf("%s[%d]", where, index)); err != nil {
				return err
			}
		}
	}

	return nil
}
