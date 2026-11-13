package hooks

func itoaFiles(number int) string {
	if number == 0 {
		return "0"
	}

	digits := ""
	for number > 0 {
		digits = string(rune('0'+number%10)) + digits
		number /= 10
	}

	return digits
}
