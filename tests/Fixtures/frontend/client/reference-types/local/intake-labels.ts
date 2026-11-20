import { CounterList } from './dispatch-board'

class IntakeTag {
    constructor(readonly text: Uint8Array) {}
}

class IntakePackage {
    constructor(readonly tag: IntakeTag) {}
}

class IntakeTrip {
    constructor(readonly active: IntakePackage) {}
}

export class IntakeLabels {
    constructor(
        private readonly received: Uint8Array[],
        // @sin UnreadCollaborator
        private readonly counter: CounterList,
    ) {}

    print(trip: IntakeTrip, send: (label: Uint8Array) => void): number {
        // @sin TrainWreck
        const caption = trip.active.tag.text
        send(caption)
        this.received.push(caption)
        return caption.byteLength
    }

    // @sin UnusedPrivateMember
    private joined(prefix: string): string {
        const decoder = new TextDecoder()
        return prefix + this.received.map(value => decoder.decode(value)).join(' / ')
    }

    // @sin UselessPropertyHook
    get width(): number {
        return 6 * 25.4
    }
}

enum IntakeStage {
    Booked = 'booked',
    AtGate = 'arrived',
    Filed = 'filed',
}

export class IntakeAssignment {
    stage = IntakeStage.Booked

    recordReceipt(): void {
        this.stage = IntakeStage.Filed
    }
}

export function recordIntake(
    assignment: IntakeAssignment, number: bigint, labels: Map<bigint, string>,
): bigint {
    if (number < 1n) return number
    // @sin GuardBesideTransition
    if (assignment.stage !== IntakeStage.AtGate) {
        throw new RangeError()
    }
    assignment.recordReceipt()
    labels.set(number, 'registered')
    return number + 1n
}

type GateNotice = { open: boolean; text: string }

export function assignGateNotice(kind: IntakeStage, waiting: bigint[], label: string): GateNotice | null {
    switch (kind) {
        case IntakeStage.Booked:
            return { open: false, text: label + ' is reserved' }
        case IntakeStage.AtGate:
            return { open: waiting.length > 0, text: 'Gate: ' + waiting.join(', ') }
        case IntakeStage.Filed:
            return { open: false, text: 'Intake is complete' }
        // @sin MatchDefaultReturnsNull
        default:
            return null
    }
}

// @sin RedundantArrowReturnType
export const waitInSeconds = (hours: number, minutes: number): number => (hours * 60 + minutes) * 60
