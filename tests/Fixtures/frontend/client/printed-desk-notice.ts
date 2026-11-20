enum DeskNoticeKind {
    Closed = 'desk closed',
    Open = 'desk open',
    Paused = 'desk paused',
}

// @righteous ConstClassEnum
export class NoticePrinter {
    static readonly paperWidth = 40

    constructor(private readonly emit: (line: string) => void) {}

    post(kind: DeskNoticeKind, desk: number): void {
        this.emit(`Desk ${desk}: ${kind}`)
    }
}

// @righteous EnumCaseOrChain
// @righteous InArrayMirrorsEnum
export function canJoin(kind: DeskNoticeKind): boolean {
    return kind === DeskNoticeKind.Open
}

// @righteous TypeSwitch
export function serveDesk(notice: { show(): string }): string {
    return notice.show()
}

/**
 * A printed desk notice copied to the queue screen.
 */
export class DeskNotice {
    constructor(readonly title: string, readonly room: number) {}

    // @righteous StackedDocblock
    // @righteous CeremonyDocblock
    // @righteous DanglingDocReference
    /**
     * Send a plain label to a {@link NoticePrinter} that owns its layout.
     */
    print(printer: NoticePrinter): void {
        printer.post(DeskNoticeKind.Open, this.room)
    }
}

// @righteous ArchaeologyComment
// @righteous NegativeSpaceComment
// @righteous RestatedComment
export function noticeCount(lines: string[], first: string): number {
    // The first line in each box belongs to the transport slip.
    return lines.filter(line => line.startsWith(first)).length
}

// @righteous RepeatedGuard
export function printDeskNotice(placed: DeskNotice, printer: NoticePrinter): void {
    if (placed.title !== '') {
        placed.print(printer)
    }
}
