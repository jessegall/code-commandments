package vue

import (


)

// TextRun is a stretch of text between two tags, its interpolations included, as written.
type TextRun struct {
	Text   string
	Static bool
}

