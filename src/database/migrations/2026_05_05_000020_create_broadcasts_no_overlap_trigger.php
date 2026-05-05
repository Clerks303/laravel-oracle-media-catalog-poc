<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Defence-in-depth: an Oracle compound trigger guarantees broadcasts on the
 * same channel cannot overlap in airtime, even if writes bypass the API
 * layer (direct SQL, batch jobs, etc.).
 *
 * Compound trigger pattern (instead of plain row-level) avoids ORA-04091
 * "table is mutating" when the AFTER STATEMENT block re-queries broadcasts.
 *
 * Adjacency is allowed: a broadcast ending at T does not overlap one starting at T.
 *
 * Skipped on non-Oracle drivers so the SQLite test suite stays green.
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'oracle') {
            return;
        }

        // Drop defensively so the migration is idempotent under migrate:fresh.
        try {
            DB::unprepared('DROP TRIGGER broadcasts_no_overlap');
        } catch (\Throwable $e) {
            // not present yet — fine
        }

        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE TRIGGER broadcasts_no_overlap
        FOR INSERT OR UPDATE OF scheduled_at, program_id, channel_id ON broadcasts
        COMPOUND TRIGGER

            TYPE row_t IS RECORD (
                id           NUMBER,
                program_id   NUMBER,
                channel_id   NUMBER,
                scheduled_at TIMESTAMP
            );
            TYPE row_tab IS TABLE OF row_t INDEX BY PLS_INTEGER;
            g_rows  row_tab;
            g_count PLS_INTEGER := 0;

            AFTER EACH ROW IS
            BEGIN
                g_count := g_count + 1;
                g_rows(g_count).id           := :NEW.id;
                g_rows(g_count).program_id   := :NEW.program_id;
                g_rows(g_count).channel_id   := :NEW.channel_id;
                g_rows(g_count).scheduled_at := :NEW.scheduled_at;
            END AFTER EACH ROW;

            AFTER STATEMENT IS
                v_new_dur NUMBER;
                v_overlap NUMBER;
            BEGIN
                FOR i IN 1 .. g_count LOOP
                    SELECT duration_min
                      INTO v_new_dur
                      FROM programs
                     WHERE id = g_rows(i).program_id;

                    SELECT COUNT(*)
                      INTO v_overlap
                      FROM broadcasts b
                      JOIN programs   p ON p.id = b.program_id
                     WHERE b.channel_id = g_rows(i).channel_id
                       AND b.id        != g_rows(i).id
                       AND b.scheduled_at < g_rows(i).scheduled_at
                            + NUMTODSINTERVAL(v_new_dur, 'MINUTE')
                       AND g_rows(i).scheduled_at < b.scheduled_at
                            + NUMTODSINTERVAL(p.duration_min, 'MINUTE');

                    IF v_overlap > 0 THEN
                        RAISE_APPLICATION_ERROR(
                            -20010,
                            'Broadcast overlaps an existing broadcast on channel '
                            || g_rows(i).channel_id
                        );
                    END IF;
                END LOOP;
            END AFTER STATEMENT;

        END broadcasts_no_overlap;
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'oracle') {
            return;
        }
        try {
            DB::unprepared('DROP TRIGGER broadcasts_no_overlap');
        } catch (\Throwable $e) {
            // already gone — fine
        }
    }
};
