package typescript

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
)

// medusaMigration is a migration as `medusa db:generate` writes it, for the table its TABLE stands for.
const medusaMigration = "import { Migration } from '@mikro-orm/migrations';\n\n" +
	"export class Migration_TABLE extends Migration {\n\n" +
	"  override async up(): Promise<void> {\n" +
	"    this.addSql(`alter table if exists \"TABLE\" drop constraint if exists \"TABLE_name_unique\";`);\n" +
	"    this.addSql(`CREATE UNIQUE INDEX IF NOT EXISTS \"IDX_TABLE_name_unique\" ON \"TABLE\" (name) WHERE deleted_at IS NULL;`);\n" +
	"    this.addSql(`create table if not exists \"TABLE\" (\"id\" text not null, \"name\" text not null, constraint \"TABLE_pkey\" primary key (\"id\"));`);\n" +
	"    this.addSql(`CREATE INDEX IF NOT EXISTS \"IDX_TABLE_deleted_at\" ON \"TABLE\" (deleted_at) WHERE deleted_at IS NULL;`);\n" +
	"  }\n\n" +
	"  override async down(): Promise<void> {\n" +
	"    this.addSql(`drop table if exists \"TABLE\" cascade;`);\n" +
	"  }\n\n" +
	"}\n"

// TestMigrationsThatFeedLiteralsToOneCalleeAreNoNearCopies holds issue 675's shape: two generated migrations whose
// up() each hands its own SQL to this.addSql share no decision to hoist, since with the SQL a parameter all that is
// left is addSql itself. The same pair building a statement from a name it computes is a near copy, and still found.
func TestMigrationsThatFeedLiteralsToOneCalleeAreNoNearCopies(t *testing.T) {
	for name, test := range map[string]struct {
		source func(table string) string
		want   int
	}{
		"literal statements": {func(table string) string {
			return strings.ReplaceAll(medusaMigration, "TABLE", table)
		}, 0},
		"a statement built from a computed name": {func(table string) string {
			computed := "Promise<void> {\n    const table = this.prefix + '" + table + "';\n    this.addSql(`truncate ${table}`);"

			return strings.Replace(strings.ReplaceAll(medusaMigration, "TABLE", table), "Promise<void> {", computed, 1)
		}, 2},
	} {
		t.Run(name, func(t *testing.T) {
			found := NearDuplicateFunctionDetector{}.Find(frontendtest.FromSource(t, map[string]string{
				"src/migrations/brand.ts":  test.source("brand"),
				"src/migrations/review.ts": test.source("review"),
			}))

			if len(found) != test.want {
				t.Fatalf("found %d near copies, want %d: %v", len(found), test.want, locations(found))
			}
		})
	}
}
