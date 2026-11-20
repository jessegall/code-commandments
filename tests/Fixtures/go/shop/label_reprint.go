package shop

import "path/filepath"

func reprintLabels(queue <-chan int, print func(string) error) int {
	completed := 0
	for copies := range queue {
		// @sin RepeatedFolderPath
		file := filepath.Join("warehouse", "saved-labels", "reprint.pdf")
		for range copies {
			if print(file) == nil {
				completed++
			}
		}
	}
	return completed
}

func reprintWhileStocked(printer *LabelPrinter, parcel *Parcel, print func()) {
	// @sin UnnamedCondition
	for printer.Paper > 0 && len(parcel.Labels) > 0 && parcel.Zone != 0 {
		print()
		printer.Paper--
	}
}

func retryPrint(printer LabelPrinter, failed <-chan string, print func(string)) {
	name, open := <-failed
	if !open {
		return
	}
	// @sin RepeatedGuard
	if printer.Paper > 0 && printer.Ready {
		print(name)
	}
}

type PrintFinish string

const matteFinish PrintFinish = "matte"

func formatPrint(word PrintFinish, send func(PrintFinish)) {
	send(word)
}

// @fixed UnnamedVocabularyLiteral
func formatPrintByName(send func(PrintFinish)) {
	formatPrint(matteFinish, send)
}

func formatPrintByLiteral(order []byte, dry func([]byte), send func(PrintFinish)) {
	if len(order) == 0 {
		return
	}
	// @sin UnnamedVocabularyLiteral
	formatPrint("matte", send)
	dry(order)
}

// @righteous UnnamedVocabularyLiteral
func formatPrintCustom(send func(PrintFinish)) {
	formatPrint("custom", send)
}

// @sin DuplicateFunction
func reprintPrice(sizes []int) int {
	total := 0
	for _, size := range sizes {
		if size > 20 {
			total += 25
			continue
		}
		total += 12
	}
	return total
}

// @sin NearDuplicateFunction
func reprintScore(labels []string) int {
	score := 23
	for _, label := range labels {
		if len(label) > 40 {
			score += 3
		} else {
			score++
		}
	}
	return score * 2
}
