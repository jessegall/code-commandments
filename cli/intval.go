package cli

import (
	"math"
	"regexp"
	"strconv"
	"strings"
)

var leadingNumber = regexp.MustCompile(`^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?`)

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
