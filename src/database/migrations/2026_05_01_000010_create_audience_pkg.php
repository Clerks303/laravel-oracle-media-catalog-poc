<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Installs the audience_stats_pkg PL/SQL package.
 * Skipped on non-Oracle connections so feature tests on SQLite still pass.
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'oracle') {
            return;
        }

        // Drop existing objects in dependency order (ignore if absent) so
        // CREATE OR REPLACE TYPE doesn't fail with ORA-02303 when the
        // package body still references the old types.
        foreach ([
            'DROP PACKAGE audience_stats_pkg',
            'DROP TYPE audience_tab FORCE',
            'DROP TYPE audience_row FORCE',
            'DROP TYPE top_program_tab FORCE',
            'DROP TYPE top_program_row FORCE',
        ] as $drop) {
            try {
                DB::unprepared($drop);
            } catch (\Throwable $e) {
                // object did not exist — fine
            }
        }

        $statements = [
            <<<SQL
            CREATE OR REPLACE TYPE audience_row AS OBJECT (
                channel_code    VARCHAR2(16),
                broadcast_count NUMBER,
                avg_duration    NUMBER
            )
            SQL,
            <<<SQL
            CREATE OR REPLACE TYPE audience_tab AS TABLE OF audience_row
            SQL,
            <<<SQL
            CREATE OR REPLACE TYPE top_program_row AS OBJECT (
                program_id        NUMBER,
                title             VARCHAR2(255),
                channel_code      VARCHAR2(16),
                broadcast_count   NUMBER,
                total_airtime_min NUMBER
            )
            SQL,
            <<<SQL
            CREATE OR REPLACE TYPE top_program_tab AS TABLE OF top_program_row
            SQL,
            <<<SQL
            CREATE OR REPLACE PACKAGE audience_stats_pkg AS
                FUNCTION per_channel(p_from IN DATE, p_to IN DATE)
                    RETURN audience_tab PIPELINED;

                FUNCTION top_programs(
                    p_from    IN DATE,
                    p_to      IN DATE,
                    p_limit   IN NUMBER   DEFAULT 10,
                    p_channel IN VARCHAR2 DEFAULT NULL
                ) RETURN top_program_tab PIPELINED;
            END audience_stats_pkg;
            SQL,
            <<<SQL
            CREATE OR REPLACE PACKAGE BODY audience_stats_pkg AS

                FUNCTION per_channel(p_from IN DATE, p_to IN DATE)
                    RETURN audience_tab PIPELINED IS
                BEGIN
                    FOR r IN (
                        SELECT c.code AS channel_code,
                               COUNT(b.id) AS broadcast_count,
                               ROUND(AVG(p.duration_min), 1) AS avg_duration
                          FROM channels c
                          LEFT JOIN broadcasts b
                                 ON b.channel_id = c.id
                                AND b.scheduled_at BETWEEN p_from AND p_to
                          LEFT JOIN programs p ON p.id = b.program_id
                         GROUP BY c.code
                         ORDER BY c.code
                    ) LOOP
                        PIPE ROW(audience_row(r.channel_code, r.broadcast_count, r.avg_duration));
                    END LOOP;
                    RETURN;
                END per_channel;

                FUNCTION top_programs(
                    p_from    IN DATE,
                    p_to      IN DATE,
                    p_limit   IN NUMBER   DEFAULT 10,
                    p_channel IN VARCHAR2 DEFAULT NULL
                ) RETURN top_program_tab PIPELINED IS
                BEGIN
                    FOR r IN (
                        SELECT p.id    AS program_id,
                               p.title AS title,
                               c.code  AS channel_code,
                               COUNT(b.id) AS broadcast_count,
                               COUNT(b.id) * NVL(p.duration_min, 0) AS total_airtime_min
                          FROM programs   p
                          JOIN broadcasts b ON b.program_id = p.id
                          JOIN channels   c ON c.id         = b.channel_id
                         WHERE b.scheduled_at BETWEEN p_from AND p_to
                           AND (p_channel IS NULL OR c.code = p_channel)
                         GROUP BY p.id, p.title, p.duration_min, c.code
                         ORDER BY total_airtime_min DESC, broadcast_count DESC, p.title ASC
                         FETCH FIRST p_limit ROWS ONLY
                    ) LOOP
                        PIPE ROW(top_program_row(
                            r.program_id, r.title, r.channel_code,
                            r.broadcast_count, r.total_airtime_min
                        ));
                    END LOOP;
                    RETURN;
                END top_programs;

            END audience_stats_pkg;
            SQL,
        ];

        foreach ($statements as $sql) {
            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'oracle') {
            return;
        }
        DB::unprepared('DROP PACKAGE audience_stats_pkg');
        DB::unprepared('DROP TYPE audience_tab FORCE');
        DB::unprepared('DROP TYPE audience_row FORCE');
        DB::unprepared('DROP TYPE top_program_tab FORCE');
        DB::unprepared('DROP TYPE top_program_row FORCE');
    }
};
