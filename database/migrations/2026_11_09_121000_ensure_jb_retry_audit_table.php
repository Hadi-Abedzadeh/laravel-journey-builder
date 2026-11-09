<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair older installations where the base package migration was skipped
 * because the original standalone journey tables already existed.
 * Additive only; does not modify or drop any existing table or row.
 */
return new class extends Migration {
    public function up(): void
    {
        $connection = config('journey_builder.connection', 'sqlsrv');
        $db = DB::connection($connection);
        $schema = Schema::connection($connection);

        if ($db->getDriverName() === 'sqlsrv') {
            // The package uses dbo for SQL Server tables.
            $db->statement(<<<'SQL'
IF OBJECT_ID(N'dbo.jb_retry_audit', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[jb_retry_audit] (
        [id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        [flow_id] BIGINT NOT NULL,
        [run_id] BIGINT NOT NULL,
        [node_id] VARCHAR(80) NOT NULL,
        [retry_kind] VARCHAR(16) NOT NULL,
        [previous_status] VARCHAR(20) NULL,
        [previous_error] NVARCHAR(2000) NULL,
        [created_at] DATETIME2(3) NOT NULL
    );
END
SQL);
            $db->statement(<<<'SQL'
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.jb_retry_audit')
      AND name = N'idx_jb_retry_flow_date'
)
BEGIN
    CREATE INDEX [idx_jb_retry_flow_date]
    ON [dbo].[jb_retry_audit] ([flow_id], [created_at]);
END
SQL);
            $db->statement(<<<'SQL'
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.jb_retry_audit')
      AND name = N'idx_jb_retry_run_date'
)
BEGIN
    CREATE INDEX [idx_jb_retry_run_date]
    ON [dbo].[jb_retry_audit] ([run_id], [created_at]);
END
SQL);
            return;
        }

        if (!$schema->hasTable('jb_retry_audit')) {
            $schema->create('jb_retry_audit', function (Blueprint $table): void {
                $table->id();
                $table->bigInteger('flow_id');
                $table->bigInteger('run_id');
                $table->string('node_id', 80);
                $table->string('retry_kind', 16);
                $table->string('previous_status', 20)->nullable();
                $table->text('previous_error')->nullable();
                $table->dateTime('created_at', 3);
                $table->index(['flow_id', 'created_at'], 'idx_jb_retry_flow_date');
                $table->index(['run_id', 'created_at'], 'idx_jb_retry_run_date');
            });
        }
    }

    // Never remove production retry history on rollback.
    public function down(): void {}
};
