<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $connection = config('journey_builder.connection', 'sqlsrv');
        $db = DB::connection($connection);
        $schema = Schema::connection($connection);

        if (!$schema->hasColumn('jb_flows', 'deleted_at')) {
            if ($db->getDriverName() === 'sqlsrv') {
                $db->statement('ALTER TABLE [dbo].[jb_flows] ADD [deleted_at] DATETIME2(3) NULL');
            } else {
                $schema->table('jb_flows', fn (Blueprint $t) => $t->dateTime('deleted_at', 3)->nullable());
            }
        }

        if (!$schema->hasColumn('jb_flows', 'deleted_from_status')) {
            if ($db->getDriverName() === 'sqlsrv') {
                $db->statement('ALTER TABLE [dbo].[jb_flows] ADD [deleted_from_status] VARCHAR(20) NULL');
            } else {
                $schema->table('jb_flows', fn (Blueprint $t) => $t->string('deleted_from_status',20)->nullable());
            }
        }

        if ($db->getDriverName() === 'sqlsrv') {
            if (!$schema->hasTable('jb_flow_revisions')) {
                $db->statement('CREATE TABLE [dbo].[jb_flow_revisions] ([id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY, [flow_id] BIGINT NOT NULL, [revision] INT NOT NULL, [graph_json] NVARCHAR(MAX) NOT NULL, [created_at] DATETIME2(3) NOT NULL)');
                $db->statement('CREATE UNIQUE INDEX [uq_jb_flow_revision] ON [dbo].[jb_flow_revisions] ([flow_id], [revision])');
            }
            if (!$schema->hasTable('jb_revision_credentials')) {
                $db->statement('CREATE TABLE [dbo].[jb_revision_credentials] ([id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY, [flow_id] BIGINT NOT NULL, [revision] INT NOT NULL, [node_id] VARCHAR(80) NOT NULL, [encrypted_token] VARCHAR(MAX) NOT NULL, [created_at] DATETIME2(3) NOT NULL)');
                $db->statement('CREATE UNIQUE INDEX [uq_jb_revision_credentials] ON [dbo].[jb_revision_credentials] ([flow_id], [revision], [node_id])');
            }
            if (!$schema->hasTable('jb_command_runs')) {
                $db->statement('CREATE TABLE [dbo].[jb_command_runs] ([id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY, [command_name] VARCHAR(32) NOT NULL, [status] VARCHAR(16) NOT NULL, [started_at] DATETIME2(3) NOT NULL, [finished_at] DATETIME2(3) NULL, [duration_ms] INT NULL, [processed] BIGINT NOT NULL DEFAULT 0, [failed] BIGINT NOT NULL DEFAULT 0, [details] NVARCHAR(2000) NULL, [error_message] NVARCHAR(2000) NULL)');
                $db->statement('CREATE INDEX [idx_jb_command_recent] ON [dbo].[jb_command_runs] ([command_name], [started_at] DESC)');
                $db->statement('CREATE INDEX [idx_jb_command_date] ON [dbo].[jb_command_runs] ([started_at])');
            }
            if (!$schema->hasTable('jb_admin_events')) {
                $db->statement('CREATE TABLE [dbo].[jb_admin_events] ([id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY, [flow_id] BIGINT NULL, [event_type] VARCHAR(40) NOT NULL, [actor] VARCHAR(100) NULL, [details] NVARCHAR(1000) NULL, [created_at] DATETIME2(3) NOT NULL)');
                $db->statement('CREATE INDEX [idx_jb_admin_events_recent] ON [dbo].[jb_admin_events] ([created_at] DESC)');
                $db->statement('CREATE INDEX [idx_jb_admin_events_flow] ON [dbo].[jb_admin_events] ([flow_id], [created_at] DESC)');
            }
            return;
        }

        if (!$schema->hasTable('jb_flow_revisions')) $schema->create('jb_flow_revisions', function (Blueprint $t) {
            $t->id(); $t->bigInteger('flow_id'); $t->integer('revision'); $t->longText('graph_json'); $t->dateTime('created_at',3); $t->unique(['flow_id','revision'],'uq_jb_flow_revision');
        });
        if (!$schema->hasTable('jb_revision_credentials')) $schema->create('jb_revision_credentials', function (Blueprint $t) {
            $t->id(); $t->bigInteger('flow_id'); $t->integer('revision'); $t->string('node_id',80); $t->longText('encrypted_token'); $t->dateTime('created_at',3); $t->unique(['flow_id','revision','node_id'],'uq_jb_revision_credentials');
        });
        if (!$schema->hasTable('jb_command_runs')) $schema->create('jb_command_runs', function (Blueprint $t) {
            $t->id(); $t->string('command_name',32); $t->string('status',16); $t->dateTime('started_at',3); $t->dateTime('finished_at',3)->nullable(); $t->integer('duration_ms')->nullable(); $t->bigInteger('processed')->default(0); $t->bigInteger('failed')->default(0); $t->text('details')->nullable(); $t->text('error_message')->nullable(); $t->index(['command_name','started_at'],'idx_jb_command_recent'); $t->index('started_at','idx_jb_command_date');
        });
        if (!$schema->hasTable('jb_admin_events')) $schema->create('jb_admin_events', function (Blueprint $t) {
            $t->id(); $t->bigInteger('flow_id')->nullable(); $t->string('event_type',40); $t->string('actor',100)->nullable(); $t->text('details')->nullable(); $t->dateTime('created_at',3); $t->index('created_at','idx_jb_admin_events_recent'); $t->index(['flow_id','created_at'],'idx_jb_admin_events_flow');
        });
    }

    public function down(): void
    {
        // Deliberately non-destructive: existing flow revisions and operational logs are retained.
    }
};
