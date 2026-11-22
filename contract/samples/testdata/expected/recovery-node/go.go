package parts

// @contract kind/function
func Good() int {
	return 1
}

// @contract fact/*/incomplete on function = true
func Broken( {
