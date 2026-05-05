-- =====================================================================
-- audience_stats_pkg
-- PL/SQL package returning broadcast/audience aggregates per channel
-- as a pipelined function — consumed by AudienceController via raw SQL.
--
-- Demonstrates: object types, pipelined table function, %ROWTYPE,
-- bind params (:p_from, :p_to). Called from Laravel with DB::select().
-- =====================================================================

-- 1) Object & collection types ----------------------------------------
CREATE OR REPLACE TYPE audience_row AS OBJECT (
    channel_code     VARCHAR2(16),
    broadcast_count  NUMBER,
    avg_duration     NUMBER
);
/

CREATE OR REPLACE TYPE audience_tab AS TABLE OF audience_row;
/

CREATE OR REPLACE TYPE top_program_row AS OBJECT (
    program_id        NUMBER,
    title             VARCHAR2(255),
    channel_code      VARCHAR2(16),
    broadcast_count   NUMBER,
    total_airtime_min NUMBER
);
/

CREATE OR REPLACE TYPE top_program_tab AS TABLE OF top_program_row;
/

-- 2) Package spec ------------------------------------------------------
CREATE OR REPLACE PACKAGE audience_stats_pkg AS
    FUNCTION per_channel(
        p_from IN DATE,
        p_to   IN DATE
    ) RETURN audience_tab PIPELINED;

    FUNCTION top_programs(
        p_from    IN DATE,
        p_to      IN DATE,
        p_limit   IN NUMBER   DEFAULT 10,
        p_channel IN VARCHAR2 DEFAULT NULL
    ) RETURN top_program_tab PIPELINED;
END audience_stats_pkg;
/

-- 3) Package body ------------------------------------------------------
CREATE OR REPLACE PACKAGE BODY audience_stats_pkg AS

    FUNCTION per_channel(
        p_from IN DATE,
        p_to   IN DATE
    ) RETURN audience_tab PIPELINED IS
    BEGIN
        FOR r IN (
            SELECT c.code AS channel_code,
                   COUNT(b.id) AS broadcast_count,
                   ROUND(AVG(p.duration_min), 1) AS avg_duration
              FROM channels  c
              LEFT JOIN broadcasts b
                     ON b.channel_id = c.id
                    AND b.scheduled_at BETWEEN p_from AND p_to
              LEFT JOIN programs p
                     ON p.id = b.program_id
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
/

-- 4) Example calls from SQL*Plus / SQL Developer ----------------------
-- SELECT * FROM TABLE(audience_stats_pkg.per_channel(SYSDATE - 30, SYSDATE));
-- SELECT * FROM TABLE(audience_stats_pkg.top_programs(SYSDATE - 30, SYSDATE, 10, NULL));
-- SELECT * FROM TABLE(audience_stats_pkg.top_programs(SYSDATE - 30, SYSDATE, 5,  'ARTEFR'));
