package python_test

import (
	"strings"

)

func filesIn[T any](want map[string]T) map[string]bool {
	files := map[string]bool{}
	for key := range want {
		path, _, _ := strings.Cut(key, "@")
		files[path] = true
	}

	return files
}
