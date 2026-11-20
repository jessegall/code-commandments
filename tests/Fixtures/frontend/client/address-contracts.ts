type AddressParts = { street: string; district: string; postcode: string }

// @righteous ArrayBag
export function selectedAddress(items: Record<string, string>, key: string): string {
    return items[key]
}

// @fixed ArrayBag
// @fixed ArrayReturnBag
export function makeAddress(street: string, district: string, postcode: string): AddressParts {
    return { street, district, postcode }
}

// @righteous ArrayReturnBag
export function rootAddress(): AddressParts {
    return { street: 'Mill Road', district: 'Eastern', postcode: '10001' }
}

// @righteous ArrayBagParameter
export function addressField(bag: Record<string, string>, name: string): string {
    return bag[name]
}

// @fixed ArrayBagParameter
export function addressLine(parts: AddressParts): string {
    return parts.street + ' ' + parts.district
}

// @righteous FeatureEnvy
export function zoneCode(parts: AddressParts): string {
    return parts.postcode.slice(0, 2)
}

class Carrier {
    constructor(private readonly parts: AddressParts) {}

    // @fixed FeatureEnvy
    line(): string {
        return this.parts.street + ' ' + this.parts.district + ' ' + this.parts.postcode
    }
}

// @righteous KeyedLookupEnvy
// @sin ParamResolvedFromParam
export function carrierByCode(carriers: Record<string, Carrier>, code: string): Carrier {
    return carriers[code]
}

// @fixed KeyedLookupEnvy
export class RoutingAddress {
    constructor(private readonly postcode: string) {}

    carrierFrom(carriers: Record<string, Carrier>): Carrier {
        return carriers[this.postcode]
    }
}

export class LocalCounters {
    private active = 0
    private name = ''

    // @righteous CoupledFields
    update(active: number, name: string): number {
        this.active = active
        this.name = name
        return this.active
    }

    // @righteous BareStatePredicate
    isOpen(): boolean {
        return this.active > 0
    }

    // @righteous NarratedCommand
    clear(): void {
        this.name = ''
    }

    // @fixed MutableStaticState
    // @righteous MutableStaticState
    increment(): void {
        this.active += 1
    }

    // @righteous ScratchStateRestore
    probeName(name: string): string {
        const original = this.name
        return original + ': ' + name
    }

    // @fixed ScratchStateRestore
    inspect(value: number): string {
        return value.toString() + this.name
    }
}

export class SelectedAddress {
    constructor(private readonly value: AddressParts) {}

    // @righteous MutableValueObject
    // @righteous HandRolledWither
    // @fixed MutableValueObject
    // @fixed HandRolledWither
    withStreet(street: string): SelectedAddress {
        return new SelectedAddress({ ...this.value, street })
    }

    equals(other: SelectedAddress): boolean {
        return this.value.postcode === other.value.postcode
    }
}

export class SavedAddresses {
    constructor(private readonly addresses: string[]) {}

    // @righteous HandedOutCollection
    addressAt(index: number): string {
        return this.addresses[index]
    }

    // @righteous DataClump
    // @fixed DataClump
    current(parts: AddressParts): string {
        return parts.postcode
    }
}

export class ReadyAddress {
    // @righteous ConstructorSideEffect
    constructor(private readonly carrier: Carrier) {}

    // @fixed ConstructorSideEffect
    dispatch(): void {
        const line = this.carrier.line()
        console.log(line)
    }
}
