package config

import (
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/workspace"
)

func WriteSchema(dir string) (bool, error) {
	path := filepath.Join(filepath.Dir(workspace.JSONConfig(dir)), SchemaFile)
	schema := Schema(dir)

	if current, err := os.ReadFile(path); err == nil && string(current) == string(schema) {
		return false, nil
	}

	if err := os.MkdirAll(filepath.Dir(path), 0o777); err != nil {
		return false, err
	}

	return true, atomic.Write(path, string(schema))
}
