package shop

func kind(code int) string {
	switch code {
	case 1, 2:
		fallthrough
	case 3:
		return "low"
	default:
		return "other"
	}
}

func shape(value any) int {
	switch typed := value.(type) {
	case int:
		return typed
	case string:
		return len(typed)
	}
	return 0
}
