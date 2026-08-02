<?php

namespace Shop\Reporting;

use Illuminate\Database\ConnectionInterface;
use Shop\Enums\ProductCategory;

/**
 * Two steps of the reparent transaction. They share nothing but the DB API they call: one
 * INSERT … ON CONFLICT, one UPDATE … FROM, on different tables with a different binding order.
 */
final class ShopResourceReparenter
{
    public function __construct(private readonly ConnectionInterface $database) {}

    /**
     * A body that is ONE call statement is a named one-line delegate — the SQL is the logic, and
     * what a literal-blind hash sees the two of these share is only the API. No marker: NOT a
     * near-duplicate, because parameterising it would hide two intent-revealing steps behind a
     * generic runner and relocate the data instead of removing a duplicate.
     */
    public function upsertTargetRows(string $target, string $source, ProductCategory $type, string $ids): void
    {
        $this->database->statement(<<<'SQL'
            INSERT INTO shop_resources (id, shop_id, resource_type, local_id)
            SELECT gen_random_uuid(), ?, sr.resource_type, sr.local_id
            FROM shop_resources sr
            WHERE sr.shop_id = ? AND sr.resource_type = ? AND sr.local_id = ANY(?)
            ON CONFLICT (shop_id, resource_type, local_id) DO NOTHING
        SQL, [$target, $source, $type->value, $ids]);
    }

    /**
     * The other step, and the far side of the same reasoning.
     */
    public function repointResourceOrigins(string $source, string $target, ProductCategory $type, string $ids): void
    {
        $this->database->statement(<<<'SQL'
            UPDATE resource_origins ro
            SET shop_resource_id = target_sr.id, updated_at = now()
            FROM shop_resources source_sr, shop_resources target_sr
            WHERE ro.shop_resource_id = source_sr.id AND source_sr.shop_id = ?
              AND target_sr.shop_id = ? AND source_sr.resource_type = ?
              AND source_sr.local_id = ANY(?)
        SQL, [$source, $target, $type->value, $ids]);
    }
}
