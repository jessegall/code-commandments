package dashboard

import (
	"bytes"
	"encoding/json"
	"fmt"
	"unicode/utf16"
)

// Object is a JSON object that keeps its keys in the order they were set, as a PHP array does.
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

// Set puts a value under the key, keeping the key's place when it is already there.
func (o *Object) Set(key string, value any) {
	if _, set := o.values[key]; !set {
		o.keys = append(o.keys, key)
	}

	o.values[key] = value
}

// MarshalJSON writes the keys in order.
func (o *Object) MarshalJSON() ([]byte, error) {
	var out bytes.Buffer
	out.WriteByte('{')

	for i, key := range o.keys {
		if i > 0 {
			out.WriteByte(',')
		}

		name, err := encode(key)
		if err != nil {
			return nil, err
		}

		value, err := encode(o.values[key])
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
	raw, err := encode(value)
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
	raw, err := encode(value)
	if err != nil || unicode {
		return string(raw), err
	}

	return escapeUnicode(string(raw)), nil
}

func encode(value any) ([]byte, error) {
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
