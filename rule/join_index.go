package rule

import (
	"fmt"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// joinIndex is a relation's rows by the values of the fields a join matches on, so a row finds its matches in one
// lookup instead of a comparison with every row. Rows keep their order under each key. A relation one of whose rows
// holds a list or a record there, which compares item by item, is scanned row by row instead.
type joinIndex struct {
	fields      []string
	expressions []Expr
	rows        map[string][]value
	records     []value
	scanned     bool
}

func indexJoin(records []value, on map[string]Expr) *joinIndex {
	fields := slices.Sorted(func(yield func(string) bool) {
		for field := range on {
			if !yield(field) {
				return
			}
		}
	})
	index := &joinIndex{fields: fields, rows: map[string][]value{}, records: records}
	for _, field := range fields {
		index.expressions = append(index.expressions, on[field])
	}
	for _, candidate := range records {
		values := make([]value, len(fields))
		for at, field := range fields {
			values[at] = read(candidate, field)
		}
		key, keyed := joinKey(values)
		if !keyed {
			index.scanned = true

			return index
		}
		if key != nil {
			index.rows[*key] = append(index.rows[*key], candidate)
		}
	}

	return index
}

func (index *joinIndex) matching(r rows, each row, every bool) []value {
	values := make([]value, len(index.expressions))
	for at, expression := range index.expressions {
		values[at] = r.evaluate(expression, each.scope())
	}
	key, keyed := joinKey(values)
	if !keyed || index.scanned {
		return index.slowly(values, every)
	}
	if key == nil {
		return nil
	}
	found := index.rows[*key]
	if !every && len(found) > 1 {
		return found[:1]
	}

	return found
}

func (index *joinIndex) slowly(values []value, every bool) []value {
	var matched []value
	for _, candidate := range index.records {
		agrees := true
		for at, field := range index.fields {
			if equal(read(candidate, field), values[at]) != engine.True {
				agrees = false

				break
			}
		}
		if agrees {
			matched = append(matched, candidate)
			if !every {
				break
			}
		}
	}

	return matched
}

func joinKey(values []value) (*string, bool) {
	var key strings.Builder
	for _, each := range values {
		if !each.known {
			return nil, true
		}
		switch held := each.held.(type) {
		case []value, record:
			return nil, false
		case engine.Match:
			fmt.Fprintf(&key, "node:%p\x00", held.Node())
		default:
			written := fmt.Sprint(plain(held))
			fmt.Fprintf(&key, "%T:%d:%s\x00", plain(held), len(written), written)
		}
	}
	written := key.String()

	return &written, true
}
