package contract

import (
	"bytes"
	_ "embed"
	"fmt"
	"sync"

	"github.com/santhosh-tekuri/jsonschema/v6"
)

//go:embed tree.schema.json
var schemaSource []byte

var (
	schemaOnce sync.Once
	schema     *jsonschema.Schema
	schemaErr  error
)

// Validate checks one line of a stream against tree.schema.json.
func Validate(line []byte) error {
	compiled, err := compiledSchema()
	if err != nil {
		return err
	}
	instance, err := jsonschema.UnmarshalJSON(bytes.NewReader(line))
	if err != nil {
		return fmt.Errorf("not JSON: %w", err)
	}

	return compiled.Validate(instance)
}

func compiledSchema() (*jsonschema.Schema, error) {
	schemaOnce.Do(func() {
		document, err := jsonschema.UnmarshalJSON(bytes.NewReader(schemaSource))
		if err != nil {
			schemaErr = fmt.Errorf("tree.schema.json: %w", err)
			return
		}
		compiler := jsonschema.NewCompiler()
		if err := compiler.AddResource("tree.schema.json", document); err != nil {
			schemaErr = err
			return
		}
		schema, schemaErr = compiler.Compile("tree.schema.json")
	})

	return schema, schemaErr
}
