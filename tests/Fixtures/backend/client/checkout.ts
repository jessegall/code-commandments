/**
 * The checkout page. The applied voucher code arrives blank when no voucher was entered, so the
 * page decodes the blank rather than reading an absence.
 */
const panel: VoucherPanelData = window.__voucherPanel;

export function voucherLabel(): string {
    return panel.appliedCode === '' ? 'Add a voucher' : panel.discountLabel;
}

/**
 * The stock feed says absence in its type, so the page asks for absence.
 */
export function stockTransport(feed: StockFeed): string {
    return feed.socket === null ? 'polling' : feed.socket;
}
