/**
 * The collection kiosk. It prints the customer's instructions when there are any — and asks the
 * blank whether there are.
 */
export function printNote(note: PickupNote): void {
    if (note.instructions !== '') {
        send(note.instructions);
    }
}

/**
 * A shelf note the till edits locally — the same field name, an entirely different shape, and no
 * server field is proven by asking about it.
 */
export function shelfLabel(ticket: PrinterSettings): string {
    return ticket.note === '' ? 'no note' : ticket.note;
}
