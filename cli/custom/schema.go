package custom

import (
	"bytes"
	"os"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/walk"
)

const SchemaReference = "../" + rule.SchemaFile

func SchemaPath(root string) string {
	return workspace.At(root, "").Shared(rule.SchemaFile)
}

func WriteSchema(root string) error {
	if !walk.Exists(workspace.CustomDir(root)) {
		return nil
	}

	schema := rule.Schema()
	if held, err := os.ReadFile(SchemaPath(root)); err == nil && bytes.Equal(held, schema) {
		return nil
	}

	return atomic.Write(SchemaPath(root), string(schema))
}
