<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Additive: no alteration of existing data, and no destructive rollback. */
return new class extends Migration {
    public function up(): void
    {
        $name = config('journey_builder.connection', 'sqlsrv');
        $db = DB::connection($name);
        $schema = Schema::connection($name);
        if ($db->getDriverName() === 'sqlsrv') {
            if (!$schema->hasTable('jb_sms_providers')) {
                $db->statement("CREATE TABLE [dbo].[jb_sms_providers] (
                    [id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    [code] VARCHAR(64) NOT NULL,
                    [name] NVARCHAR(120) NOT NULL,
                    [driver] VARCHAR(32) NOT NULL,
                    [sender] VARCHAR(24) NOT NULL,
                    [receptor_attribute] NVARCHAR(50) NOT NULL DEFAULT(N'phone'),
                    [source_sign] VARCHAR(30) NULL,
                    [encrypted_api_key] VARCHAR(MAX) NOT NULL,
                    [is_enabled] BIT NOT NULL DEFAULT(0),
                    [is_default] BIT NOT NULL DEFAULT(0),
                    [deleted_at] DATETIME2(3) NULL,
                    [created_at] DATETIME2(3) NOT NULL,
                    [updated_at] DATETIME2(3) NOT NULL
                )");
                $db->statement('CREATE UNIQUE INDEX [uq_jb_sms_provider_code] ON [dbo].[jb_sms_providers] ([code])');
                $db->statement('CREATE UNIQUE INDEX [uq_jb_sms_default] ON [dbo].[jb_sms_providers] ([is_default]) WHERE [is_default] = 1');
                $db->statement('CREATE INDEX [idx_jb_sms_provider_active] ON [dbo].[jb_sms_providers] ([is_enabled],[deleted_at])');
            }
            $columns = [
                'provider_id' => 'BIGINT NULL',
                'sender' => 'VARCHAR(24) NULL',
                'receptor' => 'VARCHAR(20) NULL',
                'message_text' => 'NVARCHAR(1800) NULL',
                'provider_message_id' => 'BIGINT NULL',
            ];
            foreach ($columns as $column => $type) {
                if (!$schema->hasColumn('jb_sms_logs', $column)) {
                    $db->statement('ALTER TABLE [dbo].[jb_sms_logs] ADD ['.$column.'] '.$type);
                }
            }
            // Inline provider_id index only after the extra column has been added.
            $index = $db->selectOne("SELECT 1 AS present FROM sys.indexes WHERE name = 'idx_jb_sms_dispatch' AND object_id = OBJECT_ID('dbo.jb_sms_logs')");
            if (!$index) $db->statement('CREATE INDEX [idx_jb_sms_dispatch] ON [dbo].[jb_sms_logs] ([status],[provider_id],[id])');
            return;
        }
        if (!$schema->hasTable('jb_sms_providers')) {
            $schema->create('jb_sms_providers', function (Blueprint $t) {
                $t->id(); $t->string('code',64)->unique(); $t->string('name',120);
                $t->string('driver',32); $t->string('sender',24);
                $t->string('receptor_attribute',50)->default('phone');
                $t->string('source_sign',30)->nullable(); $t->text('encrypted_api_key');
                $t->boolean('is_enabled')->default(false); $t->boolean('is_default')->default(false);
                $t->dateTime('deleted_at',3)->nullable(); $t->timestamps(3);
            });
        }
        $add = [
            'provider_id' => static fn (Blueprint $t) => $t->bigInteger('provider_id')->nullable(),
            'sender' => static fn (Blueprint $t) => $t->string('sender',24)->nullable(),
            'receptor' => static fn (Blueprint $t) => $t->string('receptor',20)->nullable(),
            'message_text' => static fn (Blueprint $t) => $t->string('message_text',1800)->nullable(),
            'provider_message_id' => static fn (Blueprint $t) => $t->bigInteger('provider_message_id')->nullable(),
        ];
        foreach ($add as $column => $fn) {
            if (!$schema->hasColumn('jb_sms_logs',$column)) $schema->table('jb_sms_logs',$fn);
        }
        // Non-SQL Server installations may need a DB-specific filtered unique index for the default.
    }

    public function down(): void {} // Production data must not be silently deleted by rollback.
};
