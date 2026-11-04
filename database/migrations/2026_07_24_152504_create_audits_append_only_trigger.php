<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared(<<< 'SQL'
                CREATE TRIGGER IF NOT EXISTS audits_prevent_update
                AFTER UPDATE ON audits
                BEGIN
                    SELECT RAISE(ABORT, 'audits table is append-only: UPDATE is not allowed');
                END;
                SQL);

            DB::unprepared(<<< 'SQL'
                CREATE TRIGGER IF NOT EXISTS audits_prevent_delete
                AFTER DELETE ON audits
                BEGIN
                    SELECT RAISE(ABORT, 'audits table is append-only: DELETE is not allowed');
                END;
                SQL);
        } elseif ($driver === 'mysql') {
            // Single quotes: under sql_mode=ANSI_QUOTES a double-quoted "45000" is an identifier
            // and the CREATE TRIGGER fails to parse.
            DB::unprepared(<<< 'SQL'
                CREATE TRIGGER audits_prevent_update
                BEFORE UPDATE ON audits
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'audits table is append-only: UPDATE is not allowed';
                END;
                SQL);

            DB::unprepared(<<< 'SQL'
                CREATE TRIGGER audits_prevent_delete
                BEFORE DELETE ON audits
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'audits table is append-only: DELETE is not allowed';
                END;
                SQL);
        } elseif ($driver === 'pgsql') {
            // Without this branch the audit trail stayed writable at the database level on the
            // PostgreSQL deployment; EXECUTE PROCEDURE is the spelling every supported version takes.
            DB::unprepared(<<< 'SQL'
                CREATE OR REPLACE FUNCTION audits_prevent_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'audits table is append-only: % is not allowed', TG_OP;
                END;
                $$ LANGUAGE plpgsql;
                SQL);

            DB::unprepared(<<< 'SQL'
                CREATE TRIGGER audits_prevent_update
                BEFORE UPDATE ON audits
                FOR EACH ROW EXECUTE PROCEDURE audits_prevent_mutation();
                SQL);

            DB::unprepared(<<< 'SQL'
                CREATE TRIGGER audits_prevent_delete
                BEFORE DELETE ON audits
                FOR EACH ROW EXECUTE PROCEDURE audits_prevent_mutation();
                SQL);
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite' || $driver === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audits_prevent_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audits_prevent_delete');
        } elseif ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audits_prevent_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audits_prevent_delete');
            DB::unprepared('DROP FUNCTION IF EXISTS audits_prevent_mutation()');
        }
    }
};
