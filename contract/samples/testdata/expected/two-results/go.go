package parse // @contract role/module/imports -> [imp]

import "strconv" // @label imp import

func Parse(text string) (int, error) {
	value, err := strconv.Atoi(text)
	if err != nil {
		return 0, err
	}
	return value, nil
}
