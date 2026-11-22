package contract

import (
	"slices"
	"sort"
)

func Items() []string {
	table := kinds()
	var items []string
	for name, spec := range table.Kinds {
		items = append(items, "kind/"+name)
		for role := range spec.Roles {
			items = append(items, "role/"+name+"/"+role)
		}
		for link := range spec.Links {
			items = append(items, "link/"+name+"/"+link)
		}
		for _, fact := range slices.Concat(spec.Facts, spec.Required) {
			if !slices.Contains(table.UniversalFacts, fact) {
				items = append(items, "fact/"+name+"/"+fact)
			}
		}
	}
	for _, fact := range table.UniversalFacts {
		items = append(items, "fact/*/"+fact)
	}
	for promise := range table.Promises {
		items = append(items, "promise/"+promise)
	}
	sort.Strings(items)

	return slices.Compact(items)
}

func FactItems(kind, fact string) []string {
	var items []string
	spec, found := Kind(kind)
	if found && slices.Contains(slices.Concat(spec.Facts, spec.Required), fact) && !slices.Contains(kinds().UniversalFacts, fact) {
		items = append(items, "fact/"+kind+"/"+fact)
	}
	if slices.Contains(kinds().UniversalFacts, fact) {
		items = append(items, "fact/*/"+fact)
	}

	return items
}
