type Signatory = { callsign: number; registered: boolean; expired: boolean }

function registerCode(number: number): string {
    return number.toString(16).toUpperCase()
}

function signatureOf(held: Signatory): number {
    return held.callsign
}

export function registerSigns(
    slots: Signatory[], lookup: (name: string) => void, start: number,
): number {
    let written = start
    for (let offset = slots.length - 1; offset >= start; offset--) {
        const holder = slots[offset]
        // @sin ConvertedArgument
        lookup(registerCode(signatureOf(holder)))
        written++
    }
    return written
}

export function firstSigned(held: Signatory, room: string): string {
    // @sin ConvertedArgument
    return room + '/' + registerCode(signatureOf(held))
}

function labelSignatory(held: Signatory, label: number): number {
    return held.registered ? label : -1
}

export function signedEntries(held: Signatory[], select: Set<number>): void {
    for (const name of held) {
        // @sin DerivedArgument
        select.add(labelSignatory(name, name.callsign))
    }
}

export function signOrReject(held: Signatory): number {
    // @sin DerivedArgument
    return labelSignatory(held, held.callsign)
}

function canSign(signed: boolean, expired: boolean): boolean {
    return signed && !expired
}

export function lastEligible(held: Signatory[]): Signatory | undefined {
    for (const entry of held) {
        // @sin ComputedBooleanArgument
        if (canSign(entry.registered, entry.expired)) return entry
    }
    return undefined
}

export function approveAtDesk(held: Signatory, desk: { check(ok: boolean): void }): void {
    // @sin ComputedBooleanArgument
    desk.check(canSign(held.registered, held.expired))
}

// @sin FlagArgument
export function placeCard(approved: boolean, record: Map<string, number>, label: string): void {
    if (approved) {
        record.set(label, record.size + 1)
    } else {
        record.delete(label)
    }
}

// @fixed FlagArgument
export function releaseCard(record: Map<string, number>, label: string): void {
    record.delete(label)
}

// @sin FilledParameter
function registerSignature(held: Signatory): void {
    held.registered = !held.expired
}

export function getSignedName(held: Signatory): number {
    registerSignature(held)
    return held.registered ? held.callsign : 0
}

// @sin ParamResolvedFromParam
export function signatureNumber(form: string, people: Readonly<Record<string, Signatory>>): number {
    return people[form].callsign
}

export function signatureLabel(
    actual: Signatory,
    // @sin UnusedParameter
    format: number,
): string {
    const title = actual.expired ? 'EXPIRED' : 'REGISTERED'
    return title + '/' + actual.callsign.toString(32)
}
