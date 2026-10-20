// Two migrations as a schema generator writes them: each hands its own SQL to addSql, one statement
// after another. They share a shape and differ only in their DATA. With the SQL made a parameter,
// all that is left to share is addSql itself — there is no procedure to hoist.

export abstract class SchemaMigration {
    readonly statements: string[] = []

    addSql(sql: string): void {
        this.statements.push(sql)
    }
}

export class CreateParcelsTable extends SchemaMigration {
    // @righteous NearDuplicateFunction
    up(): void {
        this.addSql('create table if not exists "parcel" ("id" text not null, "weight" integer not null)')
        this.addSql('create index if not exists "IDX_parcel_weight" on "parcel" ("weight")')
        this.addSql('alter table "parcel" add constraint "parcel_pkey" primary key ("id")')
        this.addSql('comment on table "parcel" is \'created by the schema generator\'')
    }
}

export class CreateCarriersTable extends SchemaMigration {
    // @righteous NearDuplicateFunction
    up(): void {
        this.addSql('create table if not exists "carrier" ("id" text not null, "name" text not null)')
        this.addSql('create index if not exists "IDX_carrier_name" on "carrier" ("name")')
        this.addSql('alter table "carrier" add constraint "carrier_pkey" primary key ("id")')
        this.addSql('comment on table "carrier" is \'created by the schema generator\'')
    }
}
