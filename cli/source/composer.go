package source

import (
	"encoding/json"
	"os"
	"strings"
)

func ComposerFolders(root, section string) []string {
	raw, err := os.ReadFile(root + "/composer.json")
	if err != nil {
		return nil
	}

	var manifest map[string]json.RawMessage
	var autoload struct {
		PSR4 map[string]any `json:"psr-4"`
	}

	if json.Unmarshal(raw, &manifest) != nil || json.Unmarshal(manifest[section], &autoload) != nil {
		return nil
	}

	var folders []string

	for _, paths := range autoload.PSR4 {
		for _, folder := range listOf(paths) {
			if folder = strings.Trim(folder, "/"); folder != "" {
				folders = append(folders, folder)
			}
		}
	}

	return folders
}

func composerTestFolders(root string) []string {
	return ComposerFolders(root, "autoload-dev")
}

func listOf(value any) []string {
	switch value := value.(type) {
	case string:
		return []string{value}
	case []any:
		var paths []string

		for _, each := range value {
			if path, isPath := each.(string); isPath {
				paths = append(paths, path)
			}
		}

		return paths
	default:
		return nil
	}
}
