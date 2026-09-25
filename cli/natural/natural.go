// Package natural orders text the way a person reads numbers in it, exactly as PHP's strnatcmp does:
// `a.vue:8` before `a.vue:25`.
package natural

// Compare answers -1, 0 or 1 as a sorts before, with, or after b.
func Compare(a, b string) int {
	if a == "" || b == "" {
		return sign(len(a) - len(b))
	}

	i, j := 0, 0
	leading := true

	for {
		ca, cb := at(a, i), at(b, j)

		for leading && ca == '0' && i+1 < len(a) && isDigit(a[i+1]) {
			i++
			ca = a[i]
		}

		for leading && cb == '0' && j+1 < len(b) && isDigit(b[j+1]) {
			j++
			cb = b[j]
		}

		leading = false

		for isSpace(ca) {
			i++
			ca = at(a, i)
		}

		for isSpace(cb) {
			j++
			cb = at(b, j)
		}

		if isDigit(ca) && isDigit(cb) {
			var result int

			if ca == '0' || cb == '0' {
				result = compareLeft(a, &i, b, &j)
			} else {
				result = compareRight(a, &i, b, &j)
			}

			switch {
			case result != 0:
				return result
			case i >= len(a) && j >= len(b):
				return 0
			case i >= len(a):
				return -1
			case j >= len(b):
				return 1
			}

			ca, cb = at(a, i), at(b, j)
		}

		if ca != cb {
			return sign(int(ca) - int(cb))
		}

		i++
		j++

		switch {
		case i >= len(a) && j >= len(b):
			return 0
		case i >= len(a):
			return -1
		case j >= len(b):
			return 1
		}
	}
}

// compareRight weighs two runs of digits as numbers: the longer run wins, else the first digit that
// differs.
func compareRight(a string, i *int, b string, j *int) int {
	bias := 0

	for ; ; *i, *j = *i+1, *j+1 {
		endA, endB := !isDigit(at(a, *i)), !isDigit(at(b, *j))

		switch {
		case endA && endB:
			return bias
		case endA:
			return -1
		case endB:
			return 1
		case bias == 0 && a[*i] != b[*j]:
			bias = sign(int(a[*i]) - int(b[*j]))
		}
	}
}

// compareLeft weighs two runs of digits as fractions: the first digit that differs wins.
func compareLeft(a string, i *int, b string, j *int) int {
	for ; ; *i, *j = *i+1, *j+1 {
		endA, endB := !isDigit(at(a, *i)), !isDigit(at(b, *j))

		switch {
		case endA && endB:
			return 0
		case endA:
			return -1
		case endB:
			return 1
		case a[*i] != b[*j]:
			return sign(int(a[*i]) - int(b[*j]))
		}
	}
}

// at is the byte at i, or 0 past the end, as a C string reads.
func at(text string, i int) byte {
	if i < len(text) {
		return text[i]
	}

	return 0
}

func isDigit(c byte) bool {
	return c >= '0' && c <= '9'
}

func isSpace(c byte) bool {
	return c == ' ' || c == '\t' || c == '\n' || c == '\v' || c == '\f' || c == '\r'
}

func sign(n int) int {
	switch {
	case n < 0:
		return -1
	case n > 0:
		return 1
	default:
		return 0
	}
}
