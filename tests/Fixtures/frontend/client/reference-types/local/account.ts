import { AccountReferenceTreeService } from '../ports/services'

// @sin LoneImplementerInterface
export interface AccountReferenceTreePort {
    exportAccountReferenceTree(): string
}

export class AccountReferenceTreeExporter implements AccountReferenceTreePort {
    exportAccountReferenceTree(): string {
        return 'account'
    }
}

export class AccountReferenceTreeOwner {
    constructor(
        // @sin ConcreteCrossModuleDependency
        private readonly service: AccountReferenceTreeService,
    ) {}

    caption(): string {
        return this.service.read() + '.'
    }
}

class AccountReferenceTreeSource {
    read(): string {
        return 'account'
    }

    record(name: string): string {
        return name.trim()
    }
}

// @sin MiddleMan
export class AccountReferenceTreeForwarder {
    constructor(private readonly source: AccountReferenceTreeSource) {}

    read(): string {
        return this.source.read()
    }

    record(name: string): string {
        return this.source.record(name)
    }
}

export class AccountReferenceTreeBroken extends AccountReferenceTreeSource {
    // @sin RefusedBequest
    override read(): string {
        // @sin GenericException
        // @sin MessageAtThrow
        throw new Error('Unavailable')
    }
}

// @sin HandRolledRegistry
export class AccountReferenceTreeDirectory {
    private entries: Record<string, string> = {}

    put(key: string, value: string): void {
        this.entries[key] = value
    }

    lookup(key: string): string {
        if (this.entries[key] === undefined) {
            // @sin GenericException
            // @sin MessageAtThrow
            throw new Error('Unknown key')
        }
        return this.entries[key]
    }
}

class AccountReferenceTreeLabel {
    text = 'account'
}

class AccountReferenceTreeBin {
    label = new AccountReferenceTreeLabel()
}

class AccountReferenceTreeShelf {
    bin = new AccountReferenceTreeBin()
}

export function accountRead(shelf: AccountReferenceTreeShelf): string {
    // @sin TrainWreck
    return shelf.bin.label.text
}

export class AccountReferenceTreeDisplay {
    constructor(
        // @sin UnreadCollaborator
        private readonly source: AccountReferenceTreeSource,
    ) {}

    // @sin UnusedPrivateMember
    private draft(): string {
        return 'account'
    }

    // @sin UselessPropertyHook
    get weight(): number {
        return 12
    }
}

enum AccountReferenceTreeStage {
    Open = 'open',
    Sealed = 'sealed',
}

class AccountReferenceTreeBatch {
    stage = AccountReferenceTreeStage.Open

    seal(): void {
        this.stage = AccountReferenceTreeStage.Sealed
    }
}

export function accountSeal(batch: AccountReferenceTreeBatch): void {
    // @sin GuardBesideTransition
    if (batch.stage !== AccountReferenceTreeStage.Open) {
        // @sin GenericException
        // @sin MessageAtThrow
        throw new Error('Already closed')
    }
    batch.seal()
}

// @sin RedundantArrowReturnType
export const accountScore = (value: number): number => value - 2

export function accountStageCaption(stage: AccountReferenceTreeStage): string | null {
    switch (stage) {
        case AccountReferenceTreeStage.Open: return 'open'
        case AccountReferenceTreeStage.Sealed: return 'sealed'
        // @sin MatchDefaultReturnsNull
        default: return null
    }
}
