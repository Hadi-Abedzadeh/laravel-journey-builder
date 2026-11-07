<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: existing production jb_* tables are NEVER altered or deleted.
 * SQL Server gets explicitly typed VARCHAR / NVARCHAR / BIGINT / DATETIME2(3) columns.
 * Review sql/sqlsrv_audit.sql for earlier installations; a DBA must approve any conversion.
 */
return new class extends Migration {
    public function up(): void
    {
        $connection = config('journey_builder.connection', 'sqlsrv');
        $schema = Schema::connection($connection);
        $db = DB::connection($connection);
        if ($db->getDriverName() === 'sqlsrv') {
            // Defaults to dbo: this package targets SQL Server with the dbo schema.
            if (!$schema->hasTable('jb_flows')) {
                $db->statement("CREATE TABLE [dbo].[jb_flows] ([id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY, [code] VARCHAR(100) NOT NULL, [title] NVARCHAR(200) NOT NULL, [status] VARCHAR(20) NOT NULL DEFAULT('draft'), [revision] INT NOT NULL DEFAULT(1), [graph_json] NVARCHAR(MAX) NOT NULL, [enroll_cursor_at] DATETIME2(3) NULL, [enroll_cursor_id] BIGINT NOT NULL DEFAULT(0), [created_at] DATETIME2(3) NOT NULL, [updated_at] DATETIME2(3) NOT NULL)");
                $db->statement('CREATE UNIQUE INDEX [uq_jb_flows_code] ON [dbo].[jb_flows] ([code])');
            }
            if (!$schema->hasTable('jb_runs')) {
                $db->statement("CREATE TABLE [dbo].[jb_runs] ([id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY, [flow_id] BIGINT NOT NULL, [user_id] VARCHAR(100) NOT NULL, [node_id] VARCHAR(80) NOT NULL, [state] VARCHAR(20) NOT NULL DEFAULT('ready'), [revision] INT NOT NULL DEFAULT(1), [next_run_at] DATETIME2(3) NULL, [wait_started_at] DATETIME2(3) NULL, [locked_until] DATETIME2(3) NULL, [completed_at] DATETIME2(3) NULL, [last_error] NVARCHAR(2000) NULL, [created_at] DATETIME2(3) NOT NULL, [updated_at] DATETIME2(3) NOT NULL)");
                $db->statement('CREATE UNIQUE INDEX [uq_jb_run_flow_user] ON [dbo].[jb_runs] ([flow_id],[user_id])');
                $db->statement('CREATE INDEX [idx_jb_run_due] ON [dbo].[jb_runs] ([state],[next_run_at])');
                $db->statement('CREATE INDEX [idx_jb_run_flow_state] ON [dbo].[jb_runs] ([flow_id],[state])');
            }
            if (!$schema->hasTable('jb_sms_logs')) {
                $db->statement("CREATE TABLE [dbo].[jb_sms_logs] ([id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY, [flow_id] BIGINT NOT NULL, [run_id] BIGINT NOT NULL, [node_id] VARCHAR(80) NOT NULL, [user_id] VARCHAR(100) NOT NULL, [template] VARCHAR(150) NOT NULL, [status] VARCHAR(20) NOT NULL DEFAULT('sending'), [attempts] INT NOT NULL DEFAULT(1), [http_status] SMALLINT NULL, [error_message] NVARCHAR(2000) NULL, [sent_at] DATETIME2(3) NULL, [created_at] DATETIME2(3) NOT NULL, [updated_at] DATETIME2(3) NOT NULL)");
                $db->statement('CREATE UNIQUE INDEX [uq_jb_sms_run_node] ON [dbo].[jb_sms_logs] ([run_id],[node_id])');
                $db->statement('CREATE INDEX [idx_jb_sms_status] ON [dbo].[jb_sms_logs] ([flow_id],[status],[created_at])');
            }
            if (!$schema->hasTable('jb_api_logs')) {
                $db->statement("CREATE TABLE [dbo].[jb_api_logs] ([id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY, [flow_id] BIGINT NOT NULL, [run_id] BIGINT NOT NULL, [node_id] VARCHAR(80) NOT NULL, [user_id] VARCHAR(100) NOT NULL, [method] VARCHAR(8) NOT NULL, [target_host] VARCHAR(253) NOT NULL, [status] VARCHAR(20) NOT NULL DEFAULT('sending'), [attempts] INT NOT NULL DEFAULT(1), [http_status] SMALLINT NULL, [error_message] NVARCHAR(2000) NULL, [sent_at] DATETIME2(3) NULL, [created_at] DATETIME2(3) NOT NULL, [updated_at] DATETIME2(3) NOT NULL)");
                $db->statement('CREATE UNIQUE INDEX [uq_jb_api_run_node] ON [dbo].[jb_api_logs] ([run_id],[node_id])');
                $db->statement('CREATE INDEX [idx_jb_api_flow_status] ON [dbo].[jb_api_logs] ([flow_id],[status],[created_at])');
            }
            if (!$schema->hasTable('jb_step_logs')) {
                $db->statement("CREATE TABLE [dbo].[jb_step_logs] ([id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY, [flow_id] BIGINT NOT NULL, [run_id] BIGINT NOT NULL, [user_id] VARCHAR(100) NOT NULL, [from_node_id] VARCHAR(80) NOT NULL, [to_node_id] VARCHAR(80) NOT NULL, [port] VARCHAR(20) NOT NULL, [created_at] DATETIME2(3) NOT NULL)");
                $db->statement('CREATE INDEX [idx_jb_step_flow_date] ON [dbo].[jb_step_logs] ([flow_id],[created_at])');
                $db->statement('CREATE INDEX [idx_jb_step_run_date] ON [dbo].[jb_step_logs] ([run_id],[created_at])');
            }
            if (!$schema->hasTable('jb_api_credentials')) {
                $db->statement("CREATE TABLE [dbo].[jb_api_credentials] ([id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY, [flow_id] BIGINT NOT NULL, [node_id] VARCHAR(80) NOT NULL, [encrypted_token] VARCHAR(MAX) NOT NULL, [created_at] DATETIME2(3) NOT NULL, [updated_at] DATETIME2(3) NOT NULL)");
                $db->statement('CREATE UNIQUE INDEX [uq_jb_api_credential_flow_node] ON [dbo].[jb_api_credentials] ([flow_id],[node_id])');
            }
            if (!$schema->hasTable('jb_retry_audit')) {
                $db->statement("CREATE TABLE [dbo].[jb_retry_audit] ([id] BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY, [flow_id] BIGINT NOT NULL, [run_id] BIGINT NOT NULL, [node_id] VARCHAR(80) NOT NULL, [retry_kind] VARCHAR(16) NOT NULL, [previous_status] VARCHAR(20) NULL, [previous_error] NVARCHAR(2000) NULL, [created_at] DATETIME2(3) NOT NULL)");
                $db->statement('CREATE INDEX [idx_jb_retry_flow_date] ON [dbo].[jb_retry_audit] ([flow_id],[created_at])');
                $db->statement('CREATE INDEX [idx_jb_retry_run_date] ON [dbo].[jb_retry_audit] ([run_id],[created_at])');
            }
            return;
        }
        // Portable fallback (MySQL/PostgreSQL); their VARCHAR columns are natively string().
        if (!$schema->hasTable('jb_flows')) $schema->create('jb_flows', function (Blueprint $t) { $t->id(); $t->string('code',100)->unique(); $t->string('title',200); $t->string('status',20)->default('draft'); $t->integer('revision')->default(1); $t->longText('graph_json'); $t->dateTime('enroll_cursor_at',3)->nullable(); $t->bigInteger('enroll_cursor_id')->default(0); $t->timestamps(3); });
        if (!$schema->hasTable('jb_runs')) $schema->create('jb_runs', function (Blueprint $t) { $t->id(); $t->bigInteger('flow_id'); $t->string('user_id',100); $t->string('node_id',80); $t->string('state',20)->default('ready'); $t->integer('revision')->default(1); $t->dateTime('next_run_at',3)->nullable(); $t->dateTime('wait_started_at',3)->nullable(); $t->dateTime('locked_until',3)->nullable(); $t->dateTime('completed_at',3)->nullable(); $t->text('last_error')->nullable(); $t->timestamps(3); $t->unique(['flow_id','user_id'],'uq_jb_run_flow_user'); $t->index(['state','next_run_at'],'idx_jb_run_due'); $t->index(['flow_id','state'],'idx_jb_run_flow_state'); });
        if (!$schema->hasTable('jb_sms_logs')) $schema->create('jb_sms_logs', function (Blueprint $t) { $t->id(); $t->bigInteger('flow_id'); $t->bigInteger('run_id'); $t->string('node_id',80); $t->string('user_id',100); $t->string('template',150); $t->string('status',20)->default('sending'); $t->integer('attempts')->default(1); $t->smallInteger('http_status')->nullable(); $t->text('error_message')->nullable(); $t->dateTime('sent_at',3)->nullable(); $t->timestamps(3); $t->unique(['run_id','node_id'],'uq_jb_sms_run_node'); $t->index(['flow_id','status','created_at'],'idx_jb_sms_status'); });
        if (!$schema->hasTable('jb_api_logs')) $schema->create('jb_api_logs', function (Blueprint $t) { $t->id(); $t->bigInteger('flow_id'); $t->bigInteger('run_id'); $t->string('node_id',80); $t->string('user_id',100); $t->string('method',8); $t->string('target_host',253); $t->string('status',20)->default('sending'); $t->integer('attempts')->default(1); $t->smallInteger('http_status')->nullable(); $t->text('error_message')->nullable(); $t->dateTime('sent_at',3)->nullable(); $t->timestamps(3); $t->unique(['run_id','node_id'],'uq_jb_api_run_node'); $t->index(['flow_id','status','created_at'],'idx_jb_api_flow_status'); });
        if (!$schema->hasTable('jb_step_logs')) $schema->create('jb_step_logs', function (Blueprint $t) { $t->id(); $t->bigInteger('flow_id'); $t->bigInteger('run_id'); $t->string('user_id',100); $t->string('from_node_id',80); $t->string('to_node_id',80); $t->string('port',20); $t->dateTime('created_at',3); $t->index(['flow_id','created_at'],'idx_jb_step_flow_date'); $t->index(['run_id','created_at'],'idx_jb_step_run_date'); });
        if (!$schema->hasTable('jb_api_credentials')) $schema->create('jb_api_credentials', function (Blueprint $t) { $t->id(); $t->bigInteger('flow_id'); $t->string('node_id',80); $t->text('encrypted_token'); $t->timestamps(3); $t->unique(['flow_id','node_id'],'uq_jb_api_credential_flow_node'); });
        if (!$schema->hasTable('jb_retry_audit')) $schema->create('jb_retry_audit', function (Blueprint $t) { $t->id(); $t->bigInteger('flow_id'); $t->bigInteger('run_id'); $t->string('node_id',80); $t->string('retry_kind',16); $t->string('previous_status',20)->nullable(); $t->text('previous_error')->nullable(); $t->dateTime('created_at',3); $t->index(['flow_id','created_at'],'idx_jb_retry_flow_date'); $t->index(['run_id','created_at'],'idx_jb_retry_run_date'); });
    }

    // Never destructively roll back package-managed production tables.
    public function down(): void {}
};
