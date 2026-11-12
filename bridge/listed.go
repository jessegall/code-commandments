package bridge

import (
	"strings"

	"github.com/jessegall/code-commandments/walk"
)

func Listed(paths ...string) []string {
	return ListedAs("", paths...)
}

func ListedAs(extension string, paths ...string) []string {
	var files []string
	for _, path := range paths {
		files = append(files, walk.Files(path, walk.Excluded{}, func(file string) bool { return strings.HasSuffix(file, extension) })...)
	}

	return files
}

func ListedArguments(arguments ...string) []string {
	var listed []string
	for _, argument := range arguments {
		if strings.HasPrefix(argument, "--") {
			listed = append(listed, argument)

			continue
		}
		listed = append(listed, Listed(argument)...)
	}

	return listed
}
