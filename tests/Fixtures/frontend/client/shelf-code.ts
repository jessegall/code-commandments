export class ShelfCode {
    private constructor(readonly aisle: string, readonly slot: number) {}

    static parse(written: string): ShelfCode {
        const [aisle, slot] = written.split('-', 2)

        return new ShelfCode(aisle, Number(slot))
    }
}
