package rule

import (
	"encoding/json"
	"fmt"
	"reflect"
	"strconv"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// value is what an expression gives: a node, a list of values, a text, a number, a truth, a file, a span or a type. An
// unknown value is one the tree cannot state: a compiler fact the bridge did not write, a role a parser recovered from.
type value struct {
	held  any
	known bool
}

var unknown = value{}

func known(held any) value {
	return value{held, true}
}

func truthValue(truth engine.Truth) value {
	return known(truth)
}

// truthOf is a value read as a truth; unknown for an unknown value or one that is no truth at all.
func truthOf(v value) engine.Truth {
	if !v.known {
		return engine.Unknown
	}
	switch held := v.held.(type) {
	case engine.Truth:
		return held
	case bool:
		return engine.TruthOf(held)
	}

	return engine.Unknown
}

// listOf is a value read as a list; not a list when it is none. What a node's kind does not have reads as an empty list.
func listOf(v value) ([]value, bool) {
	if v.known && v.held == nil {
		return nil, true
	}
	items, isList := v.held.([]value)

	return items, v.known && isList
}

// nodeOf is a value read as a node; not a node when it is none. What a node's kind does not have reads as no node.
func nodeOf(v value) (engine.Match, bool) {
	if v.known && v.held == nil {
		return engine.Match{}, true
	}
	match, isNode := v.held.(engine.Match)

	return match, v.known && isNode
}

// equal says whether two known values are the same: one node, lists equal item by item, records equal field by
// field, or equal plain values. An unknown item or field leaves it unknown unless another one already differs.
func equal(a, b value) engine.Truth {
	if !a.known || !b.known {
		return engine.Unknown
	}
	switch first := a.held.(type) {
	case engine.Match:
		second, alsoNode := b.held.(engine.Match)

		return engine.TruthOf(alsoNode && first.Node() == second.Node())
	case []value:
		second, alsoList := b.held.([]value)
		if !alsoList || len(first) != len(second) {
			return engine.False
		}
		answer := engine.True
		for at := range first {
			if answer = answer.And(equal(first[at], second[at])); answer == engine.False {
				break
			}
		}

		return answer
	case record:
		second, alsoRecord := b.held.(record)
		if !alsoRecord || len(first) != len(second) {
			return engine.False
		}
		answer := engine.True
		for name, field := range first {
			other, found := second[name]
			if !found {
				return engine.False
			}
			if answer = answer.And(equal(field, other)); answer == engine.False {
				break
			}
		}

		return answer
	}

	return engine.TruthOf(plain(a.held) == plain(b.held))
}

// plain is a value comparable with ==: a truth, a list or a type is written out as text.
func plain(held any) any {
	switch typed := held.(type) {
	case engine.Truth:
		return typed == engine.True
	case []value:
		items := make([]any, len(typed))
		for at, item := range typed {
			items[at] = plain(item.held)
		}
		written, _ := json.Marshal(items)

		return string(written)
	case *contract.Type:
		written, _ := json.Marshal(typed)

		return string(written)
	case record:
		fields := map[string]any{}
		for name, field := range typed {
			fields[name] = plain(field.held)
		}
		written, _ := json.Marshal(fields)

		return string(written)
	case engine.Match:
		if !typed.Exists() {
			return nil
		}

		return fmt.Sprintf("%s#%d", typed.Source().Path, typed.Node().ID)
	}

	return held
}

// key is the text a case's arms are keyed by: a text as written, a truth as true or false, a number in its shortest form.
func key(v value) (string, bool) {
	switch held := plain(v.held).(type) {
	case string:
		return held, true
	case bool:
		return strconv.FormatBool(held), true
	case float64:
		return strconv.FormatFloat(held, 'f', -1, 64), true
	case nil:
		return "null", true
	}

	return "", false
}

// factFields is each fact of a node by the name the contract gives it, found once from the contract's own fields.
var factFields = sync.OnceValue(func() map[string]int {
	fields := map[string]int{}
	written := reflect.TypeFor[contract.Facts]()
	for at := range written.NumField() {
		if name := jsonName(written.Field(at)); name != "" {
			fields[name] = at
		}
	}

	return fields
})

var typeFields = sync.OnceValue(func() map[string]int {
	fields := map[string]int{}
	written := reflect.TypeFor[contract.Type]()
	for at := range written.NumField() {
		if name := jsonName(written.Field(at)); name != "" {
			fields[name] = at
		}
	}

	return fields
})

func jsonName(field reflect.StructField) string {
	name, _, _ := strings.Cut(field.Tag.Get("json"), ",")
	if name == "-" {
		return ""
	}

	return name
}

// fact reads a node's fact by its name. A fact left out reads as empty where the file's evidence for it is complete,
// and as unknown where it is not: a compiler fact of a file whose resolver did not run.
func fact(site engine.Match, name string) value {
	at, found := factFields()[name]
	node := site.Node()
	if !found || node.Facts == nil {
		return unknown
	}
	field := reflect.ValueOf(node.Facts).Elem().Field(at)
	if field.IsZero() && !site.Source().Knows(name) {
		return unknown
	}

	return plainOf(field)
}

// plainOf is a contract field as a rule reads it: a number as a number, a pointer through to what it points at, a list
// item by item, a literal's value decoded.
func plainOf(field reflect.Value) value {
	switch held := field.Interface().(type) {
	case *contract.Value:
		if held == nil {
			return known(nil)
		}
		var decoded any
		written, _ := held.MarshalJSON()
		if err := json.Unmarshal(written, &decoded); err != nil {
			return unknown
		}

		return known(decoded)
	case *contract.Type:
		if held == nil {
			return unknown
		}

		return known(held)
	}
	switch field.Kind() {
	case reflect.Pointer:
		if field.IsNil() {
			return unknown
		}

		return plainOf(field.Elem())
	case reflect.Int, reflect.Int8, reflect.Int16, reflect.Int32, reflect.Int64:
		return known(float64(field.Int()))
	case reflect.Slice:
		items := make([]value, field.Len())
		for at := range items {
			items[at] = plainOf(field.Index(at))
		}

		return known(items)
	case reflect.String:
		return known(field.String())
	case reflect.Bool:
		return known(field.Bool())
	case reflect.Struct:
		fields := record{}
		for at := range field.NumField() {
			if name := jsonName(field.Type().Field(at)); name != "" && field.Type().Field(at).IsExported() {
				fields[name] = plainOf(field.Field(at))
			}
		}

		return known(fields)
	}

	return known(field.Interface())
}

// typeField reads a resolved type's field by its name.
func typeField(resolved *contract.Type, name string) value {
	at, found := typeFields()[name]
	if resolved == nil || !found {
		return unknown
	}

	return plainOf(reflect.ValueOf(resolved).Elem().Field(at))
}

// fileField reads what a rule may know of a file.
func fileField(file *engine.File, name string) value {
	switch name {
	case "path":
		return known(file.Path)
	case "language":
		return known(string(file.Language()))
	case "module":
		return known(file.Module)
	case "test":
		return known(file.Purpose() != engine.Ordinary)
	case "purpose":
		return known(string(file.Purpose()))
	case "errors":
		return known(float64(file.Errors))
	case "publishedBy":
		return known(file.PublishedBy)
	case "frozen":
		return known(file.IsFrozen())
	}

	return unknown
}

func (v value) String() string {
	if !v.known {
		return "unknown"
	}

	return fmt.Sprint(plain(v.held))
}
