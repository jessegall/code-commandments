package rule

import "strings"

// didYouMean is the nearest of the options to a word written wrong, said as a hint; nothing when none is near.
func didYouMean(word string, options []string) string {
	best, bestDistance := "", len(word)/3+2

	for _, option := range options {
		if option == word {
			continue
		}

		if distance := editDistance(strings.ToLower(word), strings.ToLower(option)); distance < bestDistance {
			best, bestDistance = option, distance
		}
	}

	if best == "" {
		return ""
	}

	return " — did you mean " + `"` + best + `"?`
}

func editDistance(from, to string) int {
	previous := make([]int, len(to)+1)
	for j := range previous {
		previous[j] = j
	}

	for i := 1; i <= len(from); i++ {
		current := make([]int, len(to)+1)
		current[0] = i

		for j := 1; j <= len(to); j++ {
			substitution := previous[j-1]
			if from[i-1] != to[j-1] {
				substitution++
			}

			current[j] = min(previous[j]+1, current[j-1]+1, substitution)
		}

		previous = current
	}

	return previous[len(to)]
}

func unknownKey(err error) (string, bool) {
	key, unknown := strings.CutPrefix(err.Error(), "json: unknown field ")

	return strings.Trim(key, `"`), unknown
}
