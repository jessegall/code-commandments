// Package jsonfile reads and writes a JSON file the user owns (composer.json, .claude/settings.json) the way
// the PHP tool does: keys stay in the order the file holds them, and the file is written back pretty-printed
// with four spaces, slashes and unicode as they are, and an empty object as an empty list.
package jsonfile

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"strconv"
	"strings"
	"unicode/utf16"

	"github.com/jessegall/code-commandments/cli/atomic"
)

// Object is a JSON object whose keys keep their order.
type Object struct {
	keys   []string
	values map[string]any
}

// NewObject is an object with these key-value pairs, in order.
func NewObject(pairs ...any) *Object {
	object := &Object{values: map[string]any{}}

	for i := 0; i+1 < len(pairs); i += 2 {
		object.Set(pairs[i].(string), pairs[i+1])
	}

	return object
}

// Get is the value under the key, and whether the object has it.
func (o *Object) Get(key string) (any, bool) {
	value, has := o.values[key]

	return value, has
}

// Set puts the value under the key: in its place when the key is there, last when it is new.
func (o *Object) Set(key string, value any) {
	if _, has := o.values[key]; !has {
		o.keys = append(o.keys, key)
	}

	o.values[key] = value
}

// Delete takes the key out.
func (o *Object) Delete(key string) {
	if _, has := o.values[key]; !has {
		return
	}

	delete(o.values, key)

	for i, each := range o.keys {
		if each == key {
			o.keys = append(o.keys[:i], o.keys[i+1:]...)

			break
		}
	}
}

// Keys are the object's keys, in order.
func (o *Object) Keys() []string {
	return append([]string(nil), o.keys...)
}

// Read is the object the file at path holds; false when there is no file, or it holds no object.
func Read(path string) (*Object, bool) {
	text, err := os.ReadFile(path)
	if err != nil {
		return nil, false
	}

	decoder := json.NewDecoder(bytes.NewReader(text))
	decoder.UseNumber()

	value, err := decode(decoder)
	if err != nil {
		return nil, false
	}

	object, isObject := value.(*Object)

	return object, isObject
}

// Write writes the object to path as Text prints it.
func Write(path string, object *Object) error {
	return atomic.Write(path, Text(object))
}

// Text is the object pretty-printed as the PHP tool prints it, keys in order, with a closing newline.
func Text(object *Object) string {
	var out strings.Builder
	encode(&out, object, "")
	out.WriteString("\n")

	return out.String()
}

func decode(decoder *json.Decoder) (any, error) {
	token, err := decoder.Token()
	if err != nil {
		return nil, err
	}

	switch delimiter := token.(type) {
	case json.Delim:
		switch delimiter {
		case '{':
			object := NewObject()

			for decoder.More() {
				key, err := decoder.Token()
				if err != nil {
					return nil, err
				}

				value, err := decode(decoder)
				if err != nil {
					return nil, err
				}

				object.Set(key.(string), value)
			}

			_, err := decoder.Token()

			return object, err
		case '[':
			list := []any{}

			for decoder.More() {
				value, err := decode(decoder)
				if err != nil {
					return nil, err
				}

				list = append(list, value)
			}

			_, err := decoder.Token()

			return list, err
		}
	case nil, bool, string, json.Number:
		return token, nil
	}

	return nil, errors.New("unexpected token")
}

func encode(out *strings.Builder, value any, indent string) {
	inner := indent + "    "

	switch typed := value.(type) {
	case *Object:
		if len(typed.keys) == 0 {
			out.WriteString("[]")

			return
		}

		out.WriteString("{\n")

		for i, key := range typed.keys {
			out.WriteString(inner + quote(key) + ": ")
			encode(out, typed.values[key], inner)

			if i < len(typed.keys)-1 {
				out.WriteString(",")
			}

			out.WriteString("\n")
		}

		out.WriteString(indent + "}")
	case []any:
		if len(typed) == 0 {
			out.WriteString("[]")

			return
		}

		out.WriteString("[\n")

		for i, item := range typed {
			out.WriteString(inner)
			encode(out, item, inner)

			if i < len(typed)-1 {
				out.WriteString(",")
			}

			out.WriteString("\n")
		}

		out.WriteString(indent + "]")
	case []string:
		list := make([]any, len(typed))
		for i, item := range typed {
			list[i] = item
		}

		encode(out, list, indent)
	case string:
		out.WriteString(quote(typed))
	case json.Number:
		out.WriteString(typed.String())
	case bool:
		out.WriteString(strconv.FormatBool(typed))
	case int:
		out.WriteString(strconv.Itoa(typed))
	case nil:
		out.WriteString("null")
	}
}

// quote is a string as PHP writes it with slashes and unicode unescaped.
func quote(text string) string {
	var out bytes.Buffer

	encoder := json.NewEncoder(&out)
	encoder.SetEscapeHTML(false)
	_ = encoder.Encode(text)

	return strings.TrimSuffix(out.String(), "\n")
}

// MarshalJSON writes the keys in order.
func (o *Object) MarshalJSON() ([]byte, error) {
	var out bytes.Buffer
	out.WriteByte('{')

	for i, key := range o.keys {
		if i > 0 {
			out.WriteByte(',')
		}

		name, err := marshal(key)
		if err != nil {
			return nil, err
		}

		value, err := marshal(o.values[key])
		if err != nil {
			return nil, err
		}

		out.Write(name)
		out.WriteByte(':')
		out.Write(value)
	}

	out.WriteByte('}')

	return out.Bytes(), nil
}

// Pretty is value as PHP's json_encode pretty-prints it with unescaped slashes, and unescaped unicode
// when unicode is true.
func Pretty(value any, unicode bool) (string, error) {
	raw, err := marshal(value)
	if err != nil {
		return "", err
	}

	var indented bytes.Buffer

	if err := json.Indent(&indented, raw, "", "    "); err != nil {
		return "", err
	}

	if unicode {
		return indented.String(), nil
	}

	return escapeUnicode(indented.String()), nil
}

// Compact is value as PHP's json_encode writes it on one line with unescaped slashes, and unescaped unicode
// when unicode is true.
func Compact(value any, unicode bool) (string, error) {
	raw, err := marshal(value)
	if err != nil || unicode {
		return string(raw), err
	}

	return escapeUnicode(string(raw)), nil
}

func marshal(value any) ([]byte, error) {
	var out bytes.Buffer
	encoder := json.NewEncoder(&out)
	encoder.SetEscapeHTML(false)

	if err := encoder.Encode(value); err != nil {
		return nil, err
	}

	return bytes.TrimRight(out.Bytes(), "\n"), nil
}

// escapeUnicode writes every character past ASCII as a \u escape, as PHP does by default.
func escapeUnicode(text string) string {
	var out bytes.Buffer

	for _, character := range text {
		if character < 0x80 {
			out.WriteRune(character)

			continue
		}

		for _, unit := range utf16.Encode([]rune{character}) {
			fmt.Fprintf(&out, `\u%04x`, unit)
		}
	}

	return out.String()
}
