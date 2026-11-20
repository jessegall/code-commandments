package shop

import "path/filepath"

func purgeLabels(purge func(string), expired bool) {
	if !expired {
		return
	}

	// @sin RepeatedFolderPath
	path := filepath.Join("warehouse/saved-labels", "expired.pdf")
	purge(path)
}

// @righteous RepeatedFolderPath
func purgeLabelDraft(purge func(string)) {
	purge(filepath.Join("warehouse-drafts", "label.pdf"))
}

func purgeUnusableLabel(printer *LabelPrinter, parcel *Parcel, purge func()) {
	// @sin UnnamedCondition
	if !printer.Ready || parcel.Grams <= 0 || len(parcel.Labels) == 0 {
		purge()
	}
}

// @righteous UnnamedCondition
func purgeBlankLabel(parcel *Parcel, purge func()) {
	if parcel.Grams <= 0 || len(parcel.Labels) == 0 {
		purge()
	}
}

func printPurgeReceipt(printers map[string]LabelPrinter, log func(string)) {
	for aisle, printer := range printers {
		// @sin RepeatedGuard
		if printer.Ready && printer.Paper > 0 {
			log(aisle)
		}
	}
}

// @righteous RepeatedGuard
func hasPaper(printer LabelPrinter) bool {
	return printer.Paper > 0
}

type PurgeReason string

const expiredReason PurgeReason = "expired"

func removeLabel(word PurgeReason, send func(PurgeReason)) {
	send(word)
}

// @fixed UnnamedVocabularyLiteral
func removeLabelByName(send func(PurgeReason)) {
	removeLabel(expiredReason, send)
}

func removeLabelByLiteral(labels map[string]bool, send func(PurgeReason)) int {
	count := 0
	for _, expired := range labels {
		if !expired {
			continue
		}
		// @sin UnnamedVocabularyLiteral
		removeLabel("expired", send)
		count++
	}
	return count
}

// @righteous UnnamedVocabularyLiteral
func removeLabelCustom(send func(PurgeReason)) {
	removeLabel("custom", send)
}

// @sin DuplicateFunction
func purgePrice(sizes []int) int {
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
func purgeScore(labels []string) int {
	score := 7
	for _, label := range labels {
		if len(label) > 20 {
			score += 3
		} else {
			score++
		}
	}
	return score * 2
}
