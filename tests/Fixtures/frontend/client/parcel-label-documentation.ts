// @sin BloatedDocblock
/**
 * A label identifies a parcel to the last depot.
 *
 * The dispatch clerk sorts printed labels by their destination and keeps a stack for each delivery route.
 */
export class ParcelLabel {
    constructor(readonly reference: string, readonly address: readonly string[]) {}

    lines(country: string): readonly string[] {
        if (country === '') return this.address
        return [...this.address, country.toUpperCase()]
    }
}

// @righteous BloatedDocblock
/** A handwritten label awaiting the address check. */
export type ParcelLabelDraft = { author: string; street: string; ready: boolean }

// @sin StackedDocblock
/** A record kept when a label has been accepted. */
/** Dispatch can recover the printed address if a carton is returned. */
export interface ParcelLabelReady {
    number: number
    code(): string
    previous(): readonly string[]
}

// @fixed StackedDocblock
/** A preview can be discarded before its label is printed. */
export class ParcelLabelPreview {
    pending = true
}

// @sin DanglingDocReference
/** Address fragments follow the priority named by {@link MissingLabelPrinter}. */
export function readParcelLabel(given: ParcelLabel[], destination: string): string[] {
    const labels: string[] = []
    for (const parcel of given) {
        const address = parcel.lines(destination).join('\\n')
        labels.push(parcel.reference + ': ' + address)
    }
    return labels
}

// @fixed DanglingDocReference
/** Addresses use the fields held by {@link ParcelLabel}. */
export function firstParcel(given: ParcelLabel[]): ParcelLabel | undefined {
    return given.at(0)
}

// @sin CeremonyDocblock
/**
 * @param {string[]} segments
 * @param {boolean} includeCountry
 * @returns {string}
 */
export function formatParcel(segments: string[], includeCountry: boolean): string {
    return (includeCountry ? segments : segments.slice(0, -1)).join(' / ')
}

// @fixed CeremonyDocblock
export function joinParcelAddress(segments: readonly string[]): string {
    return segments.map(value => value.trim()).filter(Boolean).join(' / ')
}

export function parcelBalance(parcel: ParcelLabel, country: string, limit: number): string {
    // @sin ArchaeologyComment
    // The address formatter was refactored after the carrier introduced tracking.
    const trimmed = parcel.lines(country).slice(0, limit)
    // @sin NegativeSpaceComment
    // The scheduling lookup is not performed on purpose.
    const text = trimmed.join('; ')
    // @sin RestatedComment
    // Return text.
    return text
}

// @fixed ArchaeologyComment
// @fixed NegativeSpaceComment
// @fixed RestatedComment
export function parcelForDisplay(parcel: ParcelLabel, country: string, limit: number): string {
    return parcel.lines(country).slice(0, limit).join('; ')
}
