package engine

import (
	"path"
	"strings"
	"unicode"
)

// SpelledFolder is a folder one site of code names: the root it starts from and the folders it writes below it.
type SpelledFolder struct {
	Root   string
	Folder []string
}

// FolderSpelling reads the folder a site of code names, and whether it names one.
type FolderSpelling func(Match) (SpelledFolder, bool)

func (m Match) SpellsAFolder() bool {
	_, spelled := m.SpelledFolder()

	return spelled
}

func (m Match) SpelledFolder() (SpelledFolder, bool) {
	if read := m.grammar().SpelledFolder; read != nil {
		return read(m)
	}

	return SpelledFolder{}, false
}

func LiteralPathBelow(root string, parts []Match) (SpelledFolder, bool) {
	segments := make([]string, 0, len(parts))
	for _, part := range parts {
		text, ok := part.Text()
		if !part.Is(Literal) || !ok || strings.HasPrefix(text, "/") {
			return SpelledFolder{}, false
		}
		segments = append(segments, text)
	}

	return FoldersBelow(root, strings.Join(segments, "/"))
}

func FoldersBelow(root, written string) (SpelledFolder, bool) {
	if !readsAsPath(written) {
		return SpelledFolder{}, false
	}
	segments := PathSegments(written)
	for len(segments) > 0 && segments[0] == ".." {
		root, segments = path.Dir(root), segments[1:]
	}
	if !strings.HasSuffix(written, "/") {
		segments = segments[:max(len(segments)-1, 0)]
	}
	folder := CleanPath(segments)
	if len(folder) == 0 {
		return SpelledFolder{}, false
	}

	return SpelledFolder{Root: root, Folder: folder}, true
}

func readsAsPath(text string) bool {
	if strings.Contains(text, "://") || strings.IndexFunc(text, unicode.IsSpace) >= 0 || strings.ContainsAny(text, "?#=%@:<>|*\"'") {
		return false
	}

	return strings.Contains(strings.Trim(text, "/"), "/") || strings.HasPrefix(text, "/") && len(text) > 1
}

func CleanPath(segments []string) []string {
	var clean []string
	for _, segment := range segments {
		switch segment {
		case "", ".":
		case "..":
			if len(clean) > 0 && clean[len(clean)-1] != ".." {
				clean = clean[:len(clean)-1]
			} else {
				clean = append(clean, segment)
			}
		default:
			clean = append(clean, segment)
		}
	}

	return clean
}

func RepeatedFolders(candidates []Match, spelled FolderSpelling, leastSites, leastScopes int) []Match {
	keys := make([][]string, len(candidates))
	sitesOf := map[string][]int{}
	for at, candidate := range candidates {
		folder, ok := spelled(candidate)
		if !ok {
			continue
		}
		for depth := 1; depth <= len(folder.Folder); depth++ {
			key := folder.Root + "\x00" + strings.Join(folder.Folder[:depth], "/")
			keys[at] = append(keys[at], key)
			sitesOf[key] = append(sitesOf[key], at)
		}
	}

	qualifies := make(map[string]bool, len(sitesOf))
	for key, sites := range sitesOf {
		qualifies[key] = spansEnough(candidates, sites, leastSites, leastScopes)
	}
	var repeated []Match
	for at, candidate := range candidates {
		for _, key := range keys[at] {
			if qualifies[key] {
				repeated = append(repeated, candidate)

				break
			}
		}
	}

	return repeated
}

func spansEnough(candidates []Match, sites []int, leastSites, leastScopes int) bool {
	if len(sites) < leastSites {
		return false
	}
	scopes := map[string]bool{}
	for _, at := range sites {
		scopes[candidates[at].File()+"\x00"+candidates[at].Scope()] = true
	}

	return len(scopes) >= leastScopes
}
