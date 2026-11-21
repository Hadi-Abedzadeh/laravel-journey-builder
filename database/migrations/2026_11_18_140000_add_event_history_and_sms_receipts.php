<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $name = config('journey_builder.connection', 'sqlsrv');
        $db = DB::connection($name);
        $schema = Schema::connection($name);
        if ($db->getDriverName() === 'sqlsrv') {
            if (!$schema->hasTable('jb_event_occurrences')) {
                $db->statement("CREATE TABLE [dbo].[jb_event_occurrences] (
                    [id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    [user_id] VARCHAR(100) NOT NULL,
                    [event_name] NVARCHAR(50) NOT NULL,
                    [sign] VARCHAR(30) NULL,
                    [payload_json] NVARCHAR(MAX) NOT NULL,
                    [occurred_at] DATETIME2(3) NOT NULL
                )");
            }
            if (!$schema->hasColumn('jb_runs', 'wait_event_cursor_id')) {
                $db->statement('ALTER TABLE [dbo].[jb_runs] ADD [wait_event_cursor_id] BIGINT NULL');
            }
            $receiptColumns = [
                'delivery_status' => 'VARCHAR(20) NULL',
                'delivery_code' => 'INT NULL',
                'delivery_checked_at' => 'DATETIME2(3) NULL',
                'delivered_at' => 'DATETIME2(3) NULL',
            ];
            foreach ($receiptColumns as $column => $type) {
                if (!$schema->hasColumn('jb_sms_logs', $column)) {
                    $db->statement('ALTER TABLE [dbo].[jb_sms_logs] ADD ['.$column.'] '.$type);
                }
            }
            $indexes = [
                ['jb_event_occurrences', 'idx_jb_event_user_event_id', '([user_id], [event_name], [id])'],
                ['jb_event_occurrences', 'idx_jb_event_date', '([occurred_at], [id])'],
                ['jb_sms_logs', 'idx_jb_sms_receipts', '([status], [provider_id], [delivery_status], [id])'],
            ];
            foreach ($indexes as [$table, $index, $cols]) {
                $found = $db->selectOne("SELECT 1 AS found FROM sys.indexes WHERE object_id = OBJECT_ID(?) AND name = ?", ['dbo.'.$table, $index]);
                if (!$found) $db->statement('CREATE INDEX ['.$index.'] ON [dbo].['.$table.'] '.$cols);
            }
            return;
        }

        if (!$schema->hasTable('jb_event_occurrences')) {
            $schema->create('jb_event_occurrences', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('user_id', 100);
                $t->string('event_name', 50);
                $t->string('sign', 30)->nullable();
                $t->text('payload_json');
                $t->dateTime('occurred_at',3);
                $t->index(['user_id','event_name','id'],'idx_jb_event_user_event_id');
                $t->index(['occurred_at','id'],'idx_jb_event_date');
            });
        }
        if (!$schema->hasColumn('jb_runs','wait_event_cursor_id')) {
            $schema->table('jb_runs', fn (Blueprint $t) => $t->unsignedBigInteger('wait_event_cursor_id')->nullable());
        }
        $cols = [
            'delivery_status' => fn(Blueprint $t) => $t->string('delivery_status',20)->nullable(),
            'delivery_code' => fn(Blueprint $t) => $t->integer('delivery_code')->nullable(),
            'delivery_checked_at' => fn(Blueprint $t) => $t->dateTime('delivery_checked_at',3)->nullable(),
            'delivered_at' => fn(Blueprint $t) => $t->dateTime('delivered_at',3)->nullable(),
        ];
        foreach ($cols as $column => $add) {
            if (!$schema->hasColumn('jb_sms_logs',$column)) $schema->table('jb_sms_logs', $add);
        }
    }

    public function down(): void {} // Never delete production history in a rollback.
};
