package custom

import (
	"bytes"
	"os"

	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/rule"
)

const SchemaReference = "../" + rule.SchemaFile

func SchemaPath(root string) string {
	return workspace.At(root, "").Shared(rule.SchemaFile)
}

func WriteSchema(root string) error {
	if _, err := os.Stat(workspace.CustomDir(root)); err != nil {
		return nil
	}

	schema := rule.Schema()
	if held, err := os.ReadFile(SchemaPath(root)); err == nil && bytes.Equal(held, schema) {
		return nil
	}

	return os.WriteFile(SchemaPath(root), schema, 0o644)
}
