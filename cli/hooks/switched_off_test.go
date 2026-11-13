package hooks

import (
	"testing"
)

func mergeOffProject(t *testing.T) (root, data string) {
	t.Helper()

	root, data = workerProject(t)
	t.Setenv(pluginData, data)

	return root, data
}
