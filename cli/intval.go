package cli

import (
	"math"
	"regexp"
	"strconv"
	"strings"
)

// leadingNumber is the numeric prefix PHP reads a string's integer from.
var leadingNumber = regexp.MustCompile(`^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?`)

// Intval is the integer PHP's intval reads from text: its leading number after any whitespace, truncated
// toward zero and held within the integer range; 0 when it opens with none.
func Intval(text string) int {
	number := leadingNumber.FindString(strings.TrimLeft(text, " \t\n\r\v\f"))

	if number == "" {
		return 0
	}

	if !strings.ContainsAny(number, ".eE") {
		value, err := strconv.ParseInt(number, 10, 64)
		if err != nil && strings.HasPrefix(number, "-") {
			return math.MinInt64
		}

		if err != nil {
			return math.MaxInt64
		}

		return int(value)
	}

	value, _ := strconv.ParseFloat(number, 64)

	if math.IsInf(value, 0) || math.IsNaN(value) || value >= math.MaxInt64 || value < math.MinInt64 {
		return 0
	}

	return int(value)
}
