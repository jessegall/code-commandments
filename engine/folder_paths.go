package engine

import (
	"strings"
)

// SpelledFolder is a folder one site of code names: the root it starts from and the folders it writes below it.
type SpelledFolder struct {
	Root   string
	Folder []string
}

// FolderSpelling reads the folder a site of code names, and whether it names one.
type FolderSpelling func(Match) (SpelledFolder, bool)

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

	var repeated []Match
	for at, candidate := range candidates {
		for _, key := range keys[at] {
			if spansEnough(candidates, sitesOf[key], leastSites, leastScopes) {
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
