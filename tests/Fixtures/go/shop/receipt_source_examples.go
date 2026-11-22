package shop

import (
	"encoding/json"
	"fmt"
)

// VerifiedReceipt ties the display to a receipt the shop can print.
type VerifiedReceipt struct {
	Sequence int
}

// @righteous DanglingDocReference
// Keep the same sequence as [VerifiedReceipt] for duplicate receipts.
func sequenceForDisplay(receipt VerifiedReceipt) string {
	return fmt.Sprintf("receipt %04d", receipt.Sequence)
}

func carriesReceipt(sequence int, indexed map[int]VerifiedReceipt) bool {
	if len(indexed) == 0 {
		return false
	}
	// @righteous NegativeSpaceComment
	// If the receipt is not in this group, try the next counter.
	if _, found := indexed[sequence]; found {
		return true
	}
	return false
}

func archiveReceipt(receipt VerifiedReceipt, index map[int]VerifiedReceipt) int {
	// @righteous ArchaeologyComment
	// When the archive used a different counter, Sequence was still the public key.
	index[receipt.Sequence] = receipt
	// @righteous RestatedComment
	// The copy keeps its public sequence so the original and the archive agree.
	return receipt.Sequence
}

// @righteous RawDecodedArrayReturn
func decodeVerifiedReceipt(data []byte) (VerifiedReceipt, error) {
	var receipt VerifiedReceipt
	if err := json.Unmarshal(data, &receipt); err != nil {
		// @righteous WrappingWithoutCause
		return VerifiedReceipt{}, fmt.Errorf("read receipt: %w", err)
	}
	return receipt, nil
}

// @righteous CapabilityProbeHub
type receiptReader struct {
	source any
}

type sequenceReader interface {
	ReceiptSequence() int
}

func (reader receiptReader) Sequence() int {
	if source, ok := reader.source.(sequenceReader); ok {
		return source.ReceiptSequence()
	}
	return 0
}

type retainedReceipt struct {
	active *VerifiedReceipt
}

func (r retainedReceipt) Sequence() (int, bool) {
	if r.active == nil {
		return 0, false
	}
	return r.active.Sequence, true
}

// @righteous DivergentTwin
func countCurrentReceipts(receipts []VerifiedReceipt, first int) int {
	count := 0
	for _, receipt := range receipts {
		if receipt.Sequence >= first {
			count++
		}
	}
	return count
}

// @righteous NearDuplicateFunction
func receiptSequenceSum(receipts []VerifiedReceipt, bin int) int {
	sum := 0
	for _, receipt := range receipts {
		if receipt.Sequence%2 == bin {
			sum += receipt.Sequence
		}
	}
	return sum
}
