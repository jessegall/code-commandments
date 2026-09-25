package source

import (
	"path"
	"path/filepath"
	"regexp"
	"strings"
)

// Excluded is the paths a project told the tool to leave alone: plain folders, matched as prefixes, and
// globs, matched against the path relative to the project and every folder above it.
type Excluded struct {
	root     string
	prefixes []string
	patterns []string
}

// Under reads relative exclusions against root.
func Under(root string, relative []string) Excluded {
	root = strings.TrimRight(realOr(root), "/")
	excluded := Excluded{root: root}

	for _, given := range relative {
		given = strings.Trim(given, "/")

		switch {
		case given == "":
		case strings.Contains(given, "*"):
			excluded.patterns = append(excluded.patterns, given)
		default:
			excluded.prefixes = append(excluded.prefixes, realOr(root+"/"+given))
		}
	}

	return excluded
}

// IsEmpty says whether nothing is excluded.
func (e Excluded) IsEmpty() bool {
	return len(e.prefixes) == 0 && len(e.patterns) == 0
}

// Covers says whether the path is excluded.
func (e Excluded) Covers(given string) bool {
	if e.IsEmpty() {
		return false
	}

	resolved, err := filepath.EvalSymlinks(given)
	if err != nil {
		resolved = strings.TrimRight(given, "/")
	} else if absolute, err := filepath.Abs(resolved); err == nil {
		resolved = absolute
	}

	for _, prefix := range e.prefixes {
		if resolved == prefix || strings.HasPrefix(resolved, prefix+"/") {
			return true
		}
	}

	return e.matchedByGlob(resolved)
}

func (e Excluded) matchedByGlob(resolved string) bool {
	candidate, under := strings.CutPrefix(resolved, e.root+"/")

	if len(e.patterns) == 0 || !under {
		return false
	}

	for candidate != "" && candidate != "." && candidate != "/" {
		for _, pattern := range e.patterns {
			if fnmatch(pattern, candidate, !strings.Contains(pattern, "**")) {
				return true
			}
		}

		parent := path.Dir(candidate)
		if parent == candidate {
			break
		}

		candidate = parent
	}

	return false
}

// fnmatch matches as the C library's does: `*` and `?` stop at a slash when pathname is set, `[…]` is a
// class, and a backslash escapes the next character.
func fnmatch(pattern, name string, pathname bool) bool {
	any, one := ".*", "."
	if pathname {
		any, one = "[^/]*", "[^/]"
	}

	var expression strings.Builder
	expression.WriteString("^")

	for i := 0; i < len(pattern); i++ {
		switch c := pattern[i]; {
		case c == '*':
			expression.WriteString(any)
		case c == '?':
			expression.WriteString(one)
		case c == '\\' && i+1 < len(pattern):
			i++
			expression.WriteString(regexp.QuoteMeta(pattern[i : i+1]))
		case c == '[':
			end := strings.IndexByte(pattern[i+1:], ']')

			if end < 0 {
				expression.WriteString(`\[`)

				continue
			}

			class := pattern[i+1 : i+1+end]
			if strings.HasPrefix(class, "!") {
				class = "^" + class[1:]
			}

			expression.WriteString("[" + class + "]")
			i += end + 1
		default:
			expression.WriteString(regexp.QuoteMeta(string(c)))
		}
	}

	matcher, err := regexp.Compile(expression.String() + "$")

	return err == nil && matcher.MatchString(name)
}

// realOr is the path with its links resolved, or the path itself when it does not exist.
func realOr(given string) string {
	resolved, err := filepath.EvalSymlinks(given)
	if err != nil {
		return given
	}

	absolute, err := filepath.Abs(resolved)
	if err != nil {
		return resolved
	}

	return absolute
}
