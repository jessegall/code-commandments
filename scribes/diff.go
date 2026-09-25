package scribes

import (
	"os"
	"os/exec"
	"strings"
)

// UnifiedDiff is the `diff -u` of every rewritten file against what is on disk, each labelled a/ and b/
// with its path relative to base.
func UnifiedDiff(rewrites Rewrites, base string) (string, error) {
	var diff strings.Builder
	for _, path := range rewrites.paths {
		one, err := unifiedDiffOf(path, rewrites.content[path], base)
		if err != nil {
			return "", err
		}
		diff.WriteString(one)
	}

	return diff.String(), nil
}

// unifiedDiffOf diffs one file's content on disk, or nothing when it is new, with its rewritten content.
func unifiedDiffOf(path, content, base string) (string, error) {
	old, err := temporary("cc-old-", readOrEmpty(path))
	if err != nil {
		return "", err
	}
	defer os.Remove(old)
	rewritten, err := temporary("cc-new-", content)
	if err != nil {
		return "", err
	}
	defer os.Remove(rewritten)

	// diff exits 1 when the files differ; what it printed is the answer either way.
	raw, _ := exec.Command("diff", "-u", old, rewritten).Output()

	relative := strings.TrimPrefix(path, base+"/")
	lines := strings.Split(string(raw), "\n")
	for _, header := range [][2]string{{"--- ", "--- a/" + relative}, {"+++ ", "+++ b/" + relative}} {
		for index, line := range lines {
			if strings.HasPrefix(line, header[0]) {
				lines[index] = header[1]

				break
			}
		}
	}

	return strings.Join(lines, "\n"), nil
}

func readOrEmpty(path string) string {
	content, err := os.ReadFile(path)
	if err != nil {
		return ""
	}

	return string(content)
}

func temporary(prefix, content string) (string, error) {
	file, err := os.CreateTemp("", prefix)
	if err != nil {
		return "", err
	}
	defer file.Close()
	if _, err := file.WriteString(content); err != nil {
		return "", err
	}

	return file.Name(), nil
}
