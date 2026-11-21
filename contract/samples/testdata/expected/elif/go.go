package sizes

func Size(n int) string {
	if n < 10 {
		return "small"
	} else if n < 100 {
		return "medium"
	} else {
		return "large"
	}
}
