import { PackingReferenceTreeService } from '../ports/services'

// @sin LoneImplementerInterface
export interface PackingReferenceTreePort {
    queue(box: number): void
    pick(count: number): number[]
}

export class PackingReferenceTreeQueue implements PackingReferenceTreePort {
    private readonly boxes: number[] = []

    queue(box: number): void {
        if (box > 0) this.boxes.push(box)
    }

    pick(count: number): number[] {
        return this.boxes.splice(0, count)
    }
}

export class PackingReferenceTreeOwner {
    private readonly unprinted: Set<number>

    constructor(
        initial: number[],
        // @sin ConcreteCrossModuleDependency
        private readonly service: PackingReferenceTreeService,
    ) {
        this.unprinted = new Set(initial)
    }

    release(out: (heading: string, box: number) => void): number {
        const heading = this.service.read()
        for (const box of this.unprinted) {
            out(heading, box)
        }
        return this.unprinted.size
    }
}

class PackingReferenceTreeSource {
    private readonly labels: string[] = []

    read(): string {
        return this.labels.join(' / ')
    }

    isPending(box: string): boolean {
        return this.labels.includes(box)
    }

    put(label: string): void {
        this.labels.push(label)
    }
}

// @sin MiddleMan
export class PackingReferenceTreeForwarder {
    constructor(private readonly record: PackingReferenceTreeSource) {}

    isPending(number: string): boolean {
        return this.record.isPending(number)
    }

    put(label: string): void {
        this.record.put(label)
    }
}

export class PackingReferenceTreeBroken extends PackingReferenceTreeSource {
    // @sin RefusedBequest
    override read(): string {
        throw new RangeError()
    }

    discard(boxes: string[]): string[] {
        return boxes.filter(box => !this.isPending(box))
    }
}

// @sin HandRolledRegistry
export class PackingReferenceTreeDirectory {
    private entries: Record<number, string[]> = {}

    save(bay: number, parcels: string[]): void {
        this.entries[bay] = parcels.slice()
    }

    pick(bay: number, send: (label: string) => void): void {
        if (this.entries[bay] === undefined) {
            // @sin MessageAtThrow
            throw new RangeError('The packing bay has no parcels')
        }
        for (const label of this.entries[bay]) {
            send(label)
        }
    }
}

class PackingLabel {
    constructor(readonly text: string) {}
}

class PackingBin {
    constructor(readonly label: PackingLabel) {}
}

class PackingShelf {
    constructor(readonly bin: PackingBin) {}
}

export function packingRead(shelves: PackingShelf[], validate: (text: string) => boolean): string[] {
    const accepted: string[] = []
    for (const shelf of shelves) {
        // @sin TrainWreck
        const text = shelf.bin.label.text
        if (validate(text)) accepted.push(text)
    }
    return accepted
}

export class PackingReferenceTreeDisplay {
    constructor(
        private readonly labels: string[],
        // @sin UnreadCollaborator
        private readonly source: PackingReferenceTreeSource,
    ) {}

    list(): string[] {
        return this.labels.slice()
    }

    // @sin UnusedPrivateMember
    private banner(heading: string): string {
        return heading + ' / ' + this.labels.length
    }

    // @sin UselessPropertyHook
    get stickerSize(): number {
        const centimetres = 18
        return centimetres * 10
    }
}

enum PackingReferenceTreeStage {
    Open = 'open',
    Sealed = 'sealed',
}

class PackingReferenceTreeBatch {
    stage = PackingReferenceTreeStage.Open

    seal(): void {
        this.stage = PackingReferenceTreeStage.Sealed
    }
}

export function packingSeal(queue: PackingReferenceTreeBatch[], audit: (index: number) => void): number {
    let sealed = 0
    for (const batch of queue) {
        // @sin GuardBesideTransition
        if (PackingReferenceTreeStage.Open !== batch.stage) {
            // @sin MessageAtThrow
            throw new RangeError('This carton is already closed')
        }
        batch.seal()
        audit(++sealed)
    }
    return sealed
}

// @sin RedundantArrowReturnType
export const canPackLooseItems = (boxes: number, loose: number): boolean => boxes * 12 >= loose

export function packingStageCaption(stage: PackingReferenceTreeStage, labels: string[]): string[] | null {
    switch (stage) {
        case PackingReferenceTreeStage.Open:
            return labels.map(label => 'pack ' + label)
        case PackingReferenceTreeStage.Sealed:
            return []
        // @sin MatchDefaultReturnsNull
        default:
            return null
    }
}
