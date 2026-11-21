package parse

import "strconv"

func Parse(text string) (int, error) {
	value, err := strconv.Atoi(text)
	if err != nil {
		return 0, err
	}
	return value, nil
}
