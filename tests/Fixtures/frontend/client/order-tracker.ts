// Tracks an order through the fulfilment screens. Its `?.` reaches are the subject: the ones on
// fields the class declares as always present defend a case the type rules out.

interface Customer {
    name: string
}

interface Shipment {
    trackingCode: string
}

export class OrderTracker {
    private readonly customer: Customer = { name: '' }

    private readonly reference: string = ''

    private shipment?: Shipment

    customerName(): string {
        // @sin DefendedCertainField
        return this.customer?.name
    }

    slug(): string {
        // @sin DefendedCertainField
        return this.reference?.toLowerCase()
    }

    trackingCode(): string {
        // @righteous DefendedCertainField
        return this.shipment?.trackingCode ?? 'pending'
    }

    displayName(): string {
        // @righteous DefendedCertainField
        return this.customer.name
    }
}
