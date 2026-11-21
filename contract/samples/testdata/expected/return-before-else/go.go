package signs

func Sign(n int) string {
	if n < 0 {
		return "negative"
	} else {
		return "positive"
	}
}
