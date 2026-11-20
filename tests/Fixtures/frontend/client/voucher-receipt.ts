export function printVoucherLines(voucher: { active: boolean; credit: number }, printer: { online: boolean; print: (credit: number) => void }, copies: number): void {
    let remaining = copies
    // @sin UnnamedCondition
    while (printer.online && voucher.active && voucher.credit > 0 && remaining > 0) {
        printer.print(voucher.credit)
        remaining--
    }
}
