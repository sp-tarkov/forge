<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') === 'sqlite') {
            throw new \Exception('This project does not support SQLite. Update to MySQL or PostgreSQL.');
        }

        if (config('database.default') === 'mysql') {
            // https://www.drupal.org/project/natsort
            DB::unprepared("
                DROP FUNCTION IF EXISTS naturalsort;
                CREATE FUNCTION naturalsort (s VARCHAR (255)) RETURNS VARCHAR (255) NO SQL DETERMINISTIC BEGIN
                    DECLARE orig VARCHAR (255) DEFAULT s;
                    DECLARE ret VARCHAR (255) DEFAULT '';
                    IF s IS NULL THEN
                        RETURN NULL;
                    ELSEIF NOT s REGEXP '[0-9]' THEN
                        SET ret = s;
                    ELSE
                        SET s = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(s, '0', '#'), '1', '#'), '2', '#'), '3', '#'), '4', '#');
                        SET s = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(s, '5', '#'), '6', '#'), '7', '#'), '8', '#'), '9', '#');
                        SET s = REPLACE(s, '.#', '##');
                        SET s = REPLACE(s, '#,#', '###');
                        BEGIN
                            DECLARE numpos INT;
                            DECLARE numlen INT;
                            DECLARE numstr VARCHAR (255);
                            lp1: LOOP
                                SET numpos = locate('#', s);
                                IF numpos = 0 THEN
                                    SET ret = concat(ret, s);
                                    LEAVE lp1;
                                END IF;
                                SET ret = concat(ret, substring(s, 1, numpos - 1));
                                SET s = substring(s, numpos);
                                SET orig = substring(orig, numpos);
                                SET numlen = char_length(s) - char_length(trim(LEADING '#' FROM s));
                                SET numstr = cast(REPLACE(substring(orig, 1, numlen), ',', '') AS DECIMAL (13, 3));
                                SET numstr = lpad(numstr, 15, '0');
                                SET ret = concat(ret, '[', numstr, ']');
                                SET s = substring(s, numlen + 1);
                                SET orig = substring(orig, numlen + 1);
                            END LOOP;
                        END;
                    END IF;
                    SET ret = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(ret, ' ', ''), ',', ''), ':', ''), '.', ''), ';', '' ), '(', ''), ')', '');
                    RETURN ret;
                END;
            ");
        }

        if (config('database.default') === 'pgsql') {
            // http://www.rhodiumtoad.org.uk/junk/naturalsort.sql
            DB::unprepared('
              create or replace function naturalsort(text)
              returns bytea language sql immutable strict as
              $f$ select string_agg(convert_to(coalesce(r[2],length(length(r[1])::text) || length(r[1])::text || r[1]),\'SQL_ASCII\'),\'\x00\')
              from regexp_matches($1, \'0*([0-9]+)|([^0-9]+)\', \'g\') r; $f$;
          ');
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'sqlite') {
            throw new \Exception('This project does not support SQLite. Update to MySQL or PostgreSQL.');
        }

        if (config('database.default') === 'mysql' || config('database.default') === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS naturalsort');
        }
    }
};
