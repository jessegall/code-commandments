package hooks

import (
	"sort"
	"strconv"
	"strings"
)

type Shortfall struct {
	Why      Narrowed
	Asked    int
	NotAsked int
}

func Unjudged(what, why, run string) string {
	said := "Code Commandments: " + what
	if why != "" {
		said += ", because " + why
	}
	said += ", so this is not a clean answer, it is a smaller one."

	if run != "" {
		said += " Run `" + run + "` for the whole answer."
	}

	return said
}

func (s Shortfall) Line(judging string) string {
	if s.Why == "" || s.NotAsked == 0 {
		return ""
	}

	whole := ""
	if judging != "" {
		whole = judging + " --changes"
	}

	return Unjudged("judged by the "+strconv.Itoa(s.Asked)+" rules that answer from one file, and the "+
		strconv.Itoa(s.NotAsked)+" that read across files were not asked", string(s.Why), whole)
}

func Shortfalls(narrowed map[string]Narrowed, asked, notAsked map[string]int, judging string) string {
	var said []string
	byLine := map[string][]string{}
	var order []string

	for file, why := range narrowed {
		line := Shortfall{why, asked[file], notAsked[file]}.Line(judging)
		if line == "" {
			continue
		}

		if _, seen := byLine[line]; !seen {
			order = append(order, line)
		}

		byLine[line] = append(byLine[line], file)
	}

	for _, line := range order {
		files := byLine[line]
		sort.Strings(files)
		said = append(said, line+" ("+strings.Join(files, ", ")+")")
	}

	sort.Strings(said)

	return strings.Join(said, "\n\n")
}
