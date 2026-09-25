package contract

import (
	"bytes"
	_ "embed"
	"encoding/json"
	"fmt"
	"reflect"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"sync"
	"unicode/utf8"
)

// The module takes no dependency, so Go never looks for one in the composer vendor/ folder beside it:
// this file checks a line against tree.schema.json with the JSON Schema keywords that schema uses.

//go:embed tree.schema.json
var schemaSource []byte

// annotations are the schema keywords that describe and never constrain.
var annotations = map[string]bool{"$schema": true, "$id": true, "title": true, "description": true, "$defs": true}

// keywords are the constraining keywords this validator applies; any other is refused when the schema loads.
var keywords = map[string]bool{
	"$ref": true, "type": true, "properties": true, "required": true, "additionalProperties": true, "enum": true, "const": true,
	"items": true, "prefixItems": true, "minItems": true, "maxItems": true, "minimum": true, "minLength": true, "pattern": true,
	"uniqueItems": true, "oneOf": true, "allOf": true, "if": true, "then": true, "minProperties": true, "maxProperties": true,
}

var (
	schemaOnce sync.Once
	schemaRoot map[string]any
	schemaErr  error
	patterns   sync.Map
)

// Validate checks one line of a stream against tree.schema.json.
func Validate(line []byte) error {
	root, err := loadedSchema()
	if err != nil {
		return err
	}
	instance, err := decodeNumbers(line)
	if err != nil {
		return fmt.Errorf("not JSON: %w", err)
	}

	return check(root, root, instance, "")
}

func loadedSchema() (map[string]any, error) {
	schemaOnce.Do(func() {
		document, err := decodeNumbers(schemaSource)
		if err != nil {
			schemaErr = fmt.Errorf("tree.schema.json: %w", err)
			return
		}
		root, ok := document.(map[string]any)
		if !ok {
			schemaErr = fmt.Errorf("tree.schema.json is not an object")
			return
		}
		if err := known(root, ""); err != nil {
			schemaErr = fmt.Errorf("tree.schema.json: %w", err)
			return
		}
		schemaRoot = root
	})

	return schemaRoot, schemaErr
}

func decodeNumbers(data []byte) (any, error) {
	decoder := json.NewDecoder(bytes.NewReader(data))
	decoder.UseNumber()
	var value any
	if err := decoder.Decode(&value); err != nil {
		return nil, err
	}

	return value, nil
}

// known refuses a schema that uses a keyword this validator would silently ignore.
func known(schema map[string]any, at string) error {
	for keyword, value := range schema {
		switch {
		case annotations[keyword] && keyword != "$defs":
		case keyword == "$defs" || keyword == "properties":
			for name, sub := range value.(map[string]any) {
				if err := known(sub.(map[string]any), at+"/"+keyword+"/"+name); err != nil {
					return err
				}
			}
		case keyword == "items" || keyword == "if" || keyword == "then":
			if err := known(value.(map[string]any), at+"/"+keyword); err != nil {
				return err
			}
		case keyword == "oneOf" || keyword == "allOf" || keyword == "prefixItems":
			for index, sub := range value.([]any) {
				if err := known(sub.(map[string]any), fmt.Sprintf("%s/%s/%d", at, keyword, index)); err != nil {
					return err
				}
			}
		case !keywords[keyword]:
			return fmt.Errorf("%s uses %q, which this validator does not apply", at, keyword)
		}
	}

	return nil
}

func check(root, schema map[string]any, value any, at string) error {
	if ref, ok := schema["$ref"].(string); ok {
		target, err := resolve(root, ref)
		if err != nil {
			return err
		}
		if err := check(root, target, value, at); err != nil {
			return err
		}
	}
	for _, rule := range []func(map[string]any, map[string]any, any, string) error{checkType, checkValue, checkObject, checkArray, checkScalar, checkCombined} {
		if err := rule(root, schema, value, at); err != nil {
			return err
		}
	}

	return nil
}

func resolve(root map[string]any, ref string) (map[string]any, error) {
	if !strings.HasPrefix(ref, "#/") {
		return nil, fmt.Errorf("the schema refers outside itself: %s", ref)
	}
	var at any = root
	for _, part := range strings.Split(ref[2:], "/") {
		object, ok := at.(map[string]any)
		if !ok {
			return nil, fmt.Errorf("the schema's %s leads nowhere", ref)
		}
		at = object[part]
	}
	target, ok := at.(map[string]any)
	if !ok {
		return nil, fmt.Errorf("the schema's %s is not a schema", ref)
	}

	return target, nil
}

func checkType(_, schema map[string]any, value any, at string) error {
	declared, ok := schema["type"]
	if !ok {
		return nil
	}
	allowed := []any{declared}
	if list, isList := declared.([]any); isList {
		allowed = list
	}
	for _, name := range allowed {
		if isType(value, name.(string)) {
			return nil
		}
	}

	return fmt.Errorf("%s: %s is not of type %v", location(at), describe(value), declared)
}

func isType(value any, name string) bool {
	switch name {
	case "object":
		_, ok := value.(map[string]any)
		return ok
	case "array":
		_, ok := value.([]any)
		return ok
	case "string":
		_, ok := value.(string)
		return ok
	case "boolean":
		_, ok := value.(bool)
		return ok
	case "null":
		return value == nil
	case "integer":
		number, ok := value.(json.Number)
		return ok && !strings.ContainsAny(number.String(), ".eE")
	case "number":
		_, ok := value.(json.Number)
		return ok
	}

	return false
}

func checkValue(_, schema map[string]any, value any, at string) error {
	if constant, ok := schema["const"]; ok && !equal(constant, value) {
		return fmt.Errorf("%s: %s is not %v", location(at), describe(value), constant)
	}
	if options, ok := schema["enum"].([]any); ok {
		for _, option := range options {
			if equal(option, value) {
				return nil
			}
		}
		return fmt.Errorf("%s: %s is none of %v", location(at), describe(value), options)
	}

	return nil
}

func checkObject(root, schema map[string]any, value any, at string) error {
	object, ok := value.(map[string]any)
	if !ok {
		return nil
	}
	required, _ := schema["required"].([]any)
	for _, name := range required {
		if _, present := object[name.(string)]; !present {
			return fmt.Errorf("%s: %q is required", location(at), name)
		}
	}
	properties, _ := schema["properties"].(map[string]any)
	for _, name := range sortedKeys(object) {
		sub, declared := properties[name]
		if !declared {
			if closed, isBool := schema["additionalProperties"].(bool); isBool && !closed {
				return fmt.Errorf("%s: %q is not a key this object has", location(at), name)
			}
			continue
		}
		if err := check(root, sub.(map[string]any), object[name], at+"/"+name); err != nil {
			return err
		}
	}
	if least, ok := count(schema, "minProperties"); ok && len(object) < least {
		return fmt.Errorf("%s: an object with at least %d keys, not %d", location(at), least, len(object))
	}
	if most, ok := count(schema, "maxProperties"); ok && len(object) > most {
		return fmt.Errorf("%s: an object with at most %d keys, not %d", location(at), most, len(object))
	}

	return nil
}

func checkArray(root, schema map[string]any, value any, at string) error {
	array, ok := value.([]any)
	if !ok {
		return nil
	}
	if least, ok := count(schema, "minItems"); ok && len(array) < least {
		return fmt.Errorf("%s: at least %d items, not %d", location(at), least, len(array))
	}
	if most, ok := count(schema, "maxItems"); ok && len(array) > most {
		return fmt.Errorf("%s: at most %d items, not %d", location(at), most, len(array))
	}
	prefix, _ := schema["prefixItems"].([]any)
	for index, item := range array {
		sub, hasSub := schema["items"].(map[string]any)
		if index < len(prefix) {
			sub, hasSub = prefix[index].(map[string]any), true
		}
		if !hasSub {
			continue
		}
		if err := check(root, sub, item, at+"/"+strconv.Itoa(index)); err != nil {
			return err
		}
	}
	if unique, _ := schema["uniqueItems"].(bool); unique {
		for left := range array {
			for right := left + 1; right < len(array); right++ {
				if equal(array[left], array[right]) {
					return fmt.Errorf("%s: %s appears twice", location(at), describe(array[left]))
				}
			}
		}
	}

	return nil
}

func checkScalar(_, schema map[string]any, value any, at string) error {
	if text, ok := value.(string); ok {
		if least, has := count(schema, "minLength"); has && utf8.RuneCountInString(text) < least {
			return fmt.Errorf("%s: a string of at least %d characters", location(at), least)
		}
		if pattern, has := schema["pattern"].(string); has && !compiled(pattern).MatchString(text) {
			return fmt.Errorf("%s: %q does not match %s", location(at), text, pattern)
		}
	}
	if number, ok := value.(json.Number); ok {
		if floor, has := schema["minimum"].(json.Number); has {
			given, _ := number.Float64()
			least, _ := floor.Float64()
			if given < least {
				return fmt.Errorf("%s: %s is below %s", location(at), number, floor)
			}
		}
	}

	return nil
}

func checkCombined(root, schema map[string]any, value any, at string) error {
	if all, ok := schema["allOf"].([]any); ok {
		for _, sub := range all {
			if err := check(root, sub.(map[string]any), value, at); err != nil {
				return err
			}
		}
	}
	if condition, ok := schema["if"].(map[string]any); ok && check(root, condition, value, at) == nil {
		if then, has := schema["then"].(map[string]any); has {
			if err := check(root, then, value, at); err != nil {
				return err
			}
		}
	}
	if options, ok := schema["oneOf"].([]any); ok {
		matched := 0
		var closest error
		for _, sub := range options {
			err := check(root, sub.(map[string]any), value, at)
			if err == nil {
				matched++
				continue
			}
			if closest == nil || claims(sub.(map[string]any), value) {
				closest = err
			}
		}
		if matched == 0 {
			return closest
		}
		if matched > 1 {
			return fmt.Errorf("%s: matches %d of the shapes it may take, not exactly one", location(at), matched)
		}
	}

	return nil
}

// claims says whether value holds every key the schema requires, so its error is the one worth reporting.
func claims(schema map[string]any, value any) bool {
	object, ok := value.(map[string]any)
	if !ok {
		return false
	}
	required, _ := schema["required"].([]any)
	for _, name := range required {
		if _, present := object[name.(string)]; !present {
			return false
		}
	}

	return len(required) > 0
}

func count(schema map[string]any, keyword string) (int, bool) {
	number, ok := schema[keyword].(json.Number)
	if !ok {
		return 0, false
	}
	value, err := number.Int64()

	return int(value), err == nil
}

func compiled(pattern string) *regexp.Regexp {
	if cached, ok := patterns.Load(pattern); ok {
		return cached.(*regexp.Regexp)
	}
	expression := regexp.MustCompile(pattern)
	patterns.Store(pattern, expression)

	return expression
}

func equal(left, right any) bool {
	leftNumber, leftIsNumber := left.(json.Number)
	rightNumber, rightIsNumber := right.(json.Number)
	if leftIsNumber && rightIsNumber {
		a, _ := leftNumber.Float64()
		b, _ := rightNumber.Float64()
		return a == b
	}

	return reflect.DeepEqual(left, right)
}

func sortedKeys(object map[string]any) []string {
	keys := make([]string, 0, len(object))
	for key := range object {
		keys = append(keys, key)
	}
	sort.Strings(keys)

	return keys
}

func location(at string) string {
	if at == "" {
		return "the line"
	}

	return at
}

func describe(value any) string {
	encoded, err := json.Marshal(value)
	if err != nil {
		return fmt.Sprint(value)
	}
	if len(encoded) > 60 {
		return string(encoded[:57]) + "..."
	}

	return string(encoded)
}
