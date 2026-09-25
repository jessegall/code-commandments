package config

import (
	"errors"
	"os"
	"path/filepath"

	"github.com/jessegall/code-commandments/cli/workspace"
)

// Backup is the name a migrated config.php is kept under, beside the config.json that replaces it.
const Backup = "config.php.bak"

// Migrate gives a project that keeps a config.php and no config.json the config.json that says the same,
// read through the tree, and keeps the config.php as a backup; false when there is nothing to migrate. A
// config.php the tree cannot read is left where it is, and the error says why.
func Migrate(dir string) (bool, error) {
	php, json := workspace.Config(dir), workspace.JSONConfig(dir)

	if _, err := os.Stat(json); !errors.Is(err, os.ErrNotExist) {
		return false, nil
	}

	if _, err := os.Stat(php); err != nil {
		return false, nil
	}

	config, err := ReadPHP(php)
	if err != nil {
		return false, err
	}

	if err := (jsonConfig{json}).write(config); err != nil {
		return false, err
	}

	return true, os.Rename(php, filepath.Join(filepath.Dir(php), Backup))
}

// WriteSchema writes the schema a config.json names beside it, so an editor completes the rules this
// version of the tool ships; false when it already holds them.
func WriteSchema(dir string) (bool, error) {
	path := filepath.Join(filepath.Dir(workspace.JSONConfig(dir)), SchemaFile)
	schema := Schema()

	if current, err := os.ReadFile(path); err == nil && string(current) == string(schema) {
		return false, nil
	}

	if err := os.MkdirAll(filepath.Dir(path), 0o777); err != nil {
		return false, err
	}

	return true, os.WriteFile(path, schema, 0o644)
}
