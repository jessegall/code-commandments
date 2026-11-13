package walk

import (
	"path"
	"regexp"
	"strings"
	"sync"
)

// Excluded is the paths a project told the tool to leave alone: plain folders, matched as prefixes, and globs, matched against the path relative to the project and every folder above it.
type Excluded struct {
	root     string
	prefixes []string
	patterns []string
	judged   []string
}

func Under(root string, relative []string) Excluded {
	root = strings.TrimRight(Real(root), "/")
	excluded := Excluded{root: root}

	for _, given := range relative {
		given = strings.Trim(given, "/")

		switch {
		case given == "":
		case strings.Contains(given, "*"):
			excluded.patterns = append(excluded.patterns, given)
		default:
			excluded.prefixes = append(excluded.prefixes, Real(root+"/"+given))
		}
	}

	return excluded
}

func (e Excluded) Judging(folders []string) Excluded {
	judged := e.judged
	for _, given := range folders {
		if given = strings.Trim(given, "/"); given != "" {
			judged = append(judged, Real(e.root+"/"+given))
		}
	}
	e.judged = judged

	return e
}

func (e Excluded) IsEmpty() bool {
	return len(e.prefixes) == 0 && len(e.patterns) == 0
}

func (e Excluded) Covers(given string) bool {
	if e.IsEmpty() {
		return false
	}

	resolved, err := Resolved(given)
	if err != nil {
		resolved = strings.TrimRight(given, "/")
	}

	for _, judged := range e.judged {
		if resolved == judged {
			return false
		}

		if strings.HasPrefix(resolved, judged+"/") {
			return e.matchedByGlob(resolved, judged)
		}
	}

	for _, prefix := range e.prefixes {
		if resolved == prefix || strings.HasPrefix(resolved, prefix+"/") {
			return true
		}
	}

	return e.matchedByGlob(resolved, "")
}

func (e Excluded) matchedByGlob(resolved, stoppingAt string) bool {
	candidate, under := strings.CutPrefix(resolved, e.root+"/")

	if len(e.patterns) == 0 || !under {
		return false
	}

	for candidate != "" && candidate != "." && candidate != "/" && e.root+"/"+candidate != stoppingAt {
		for _, pattern := range e.patterns {
			if fnmatch(pattern, candidate, !strings.Contains(pattern, "**")) {
				return true
			}

			if anywhere, named := strings.CutPrefix(pattern, "**/"); named && fnmatch(anywhere, candidate, false) {
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

	matcher, err := compiled(expression.String() + "$")

	return err == nil && matcher.MatchString(name)
}

// patterns holds each exclusion pattern compiled once a process: the walker matches every pattern against every folder
// above every file it meets.
var patterns sync.Map

func compiled(expression string) (*regexp.Regexp, error) {
	if held, ok := patterns.Load(expression); ok {
		return held.(*regexp.Regexp), nil
	}
	matcher, err := regexp.Compile(expression)
	if err != nil {
		return nil, err
	}
	patterns.Store(expression, matcher)

	return matcher, nil
}
