package rule

import (
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/engine"
)

// textIndex is a long list of known texts held two ways, so a rule asking of it once a row reads it once a codebase:
// as a set, for has, and in order, for hasStartingWith.
type textIndex struct {
	items  []value
	set    map[string]bool
	sorted []string
}

// shortestIndexed is the length below which a list is read item by item: building an index for it costs more.
const shortestIndexed = 32

type listIdentity struct {
	first *value
	count int
}

type textIndexesKey struct{}

// textIndexOf is the index of a list of known texts, built once for the list a codebase holds; none for a short list
// or one holding anything but known texts. The index keeps its list, so no other list takes its place in memory.
func (e evaluation) textIndexOf(items []value) *textIndex {
	if len(items) < shortestIndexed {
		return nil
	}
	if e.codebase == nil {
		return indexTexts(items)
	}
	indexes := engine.Analysis(e.codebase, textIndexesKey{}, func(*engine.Codebase) *sync.Map { return &sync.Map{} })
	identity := listIdentity{&items[0], len(items)}
	if held, found := indexes.Load(identity); found {
		return held.(*textIndex)
	}
	held, _ := indexes.LoadOrStore(identity, indexTexts(items))

	return held.(*textIndex)
}

func indexTexts(items []value) *textIndex {
	index := &textIndex{items: items, set: make(map[string]bool, len(items)), sorted: make([]string, 0, len(items))}
	for _, item := range items {
		written, isText := item.held.(string)
		if !item.known || !isText {
			return nil
		}
		index.set[written] = true
		index.sorted = append(index.sorted, written)
	}
	slices.Sort(index.sorted)

	return index
}

func (index *textIndex) holds(text string) bool {
	return index.set[text]
}

func (index *textIndex) holdsStartingWith(part string) bool {
	at, _ := slices.BinarySearch(index.sorted, part)

	return at < len(index.sorted) && strings.HasPrefix(index.sorted[at], part)
}

// prefixesOf is each part of a text that ends before one of its separators, shortest first: a.b.c reads a and a.b.
func prefixesOf(text, separator string) []value {
	var prefixes []value
	for at := strings.Index(text, separator); at >= 0; {
		prefixes = append(prefixes, known(text[:at]))
		next := strings.Index(text[at+len(separator):], separator)
		if next < 0 {
			break
		}
		at += len(separator) + next
	}

	return prefixes
}
