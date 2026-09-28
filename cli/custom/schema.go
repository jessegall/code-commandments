package custom

import (
	"bytes"
	"os"

	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/rule"
)

// SchemaReference is how a rule in the custom folder names the schema, for an editor to autocomplete and check it.
const SchemaReference = "../" + rule.SchemaFile

// SchemaPath is where the rule schema is written for the project at root: beside its custom folder, generated
// and ignored like the rest of .commandments.
func SchemaPath(root string) string {
	return workspace.At(root, "").Shared(rule.SchemaFile)
}

// WriteSchema writes the rule schema of the tool that runs, for a project with a custom folder; a project with
// none, or one whose schema is already this one, is left alone.
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
