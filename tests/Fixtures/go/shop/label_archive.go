package shop

import "path/filepath"

var savedLabels = filepath.Join("warehouse", "saved-labels")

func archiveLabel(save func(string, []byte) error, printed []byte) error {
	if len(printed) == 0 {
		return nil
	}
	// @sin RepeatedFolderPath
	name := filepath.Join("warehouse", "saved-labels", "archive.pdf")
	return save(name, printed)
}

// @fixed RepeatedFolderPath
func archiveSavedLabel(save func(string, []byte) error, printed []byte) error {
	return save(filepath.Join(savedLabels, "archive.pdf"), printed)
}

func archiveWhenReady(label *Parcel, printer *LabelPrinter, archive func()) {
	// @sin UnnamedCondition
	if label.Grams > 0 && printer.Ready && printer.Paper > 0 {
		archive()
	}
}

type LabelPrinter struct {
	Ready bool
	Paper int
}

// @fixed UnnamedCondition
func archiveWhenPrintable(label *Parcel, printer *LabelPrinter, archive func()) {
	canArchive := label.Grams > 0 && printer.Ready && printer.Paper > 0
	if canArchive {
		archive()
	}
}

func stockedPrinter(printer LabelPrinter) bool {
	// @sin RepeatedGuard
	return printer.Ready && printer.Paper > 0
}

// @fixed RepeatedGuard
func archiveOnStockedPrinter(printer LabelPrinter, archive func()) {
	if printer.canPrint() {
		archive()
	}
}

func (printer LabelPrinter) canPrint() bool {
	ready := printer.Ready && printer.Paper > 0
	return ready
}

type LabelMode string

const archiveMode LabelMode = "archive"

func saveLabel(word LabelMode, send func(LabelMode)) {
	send(word)
}

// @fixed UnnamedVocabularyLiteral
func saveLabelByName(send func(LabelMode)) {
	saveLabel(archiveMode, send)
}

func saveLabelByLiteral(send func(LabelMode)) {
	// @sin UnnamedVocabularyLiteral
	saveLabel("archive", send)
}

// @righteous UnnamedVocabularyLiteral
func saveLabelCustom(send func(LabelMode)) {
	saveLabel("custom", send)
}

// @sin DuplicateFunction
func archivePrice(sizes []int) int {
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
func archiveScore(labels []string) int {
	score := 15
	for _, label := range labels {
		if len(label) > 30 {
			score += 3
		} else {
			score++
		}
	}
	return score * 2
}

// @fixed DuplicateFunction
func sharedArchivePrice(sizes []int) int {
	return archivePrice(sizes)
}

// @fixed NearDuplicateFunction
func archiveScoreFor(labels []string, initial, longLabelSize int) int {
	score := initial
	for _, label := range labels {
		if len(label) > longLabelSize {
			score += 3
		} else {
			score++
		}
	}
	return score * 2
}

// @righteous DuplicateFunction
func priceOfSmallLabel() int {
	return 12
}
