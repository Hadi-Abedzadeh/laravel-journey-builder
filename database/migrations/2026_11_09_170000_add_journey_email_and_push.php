<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Additive only; preserving existing flow and delivery data is intentional. */
return new class extends Migration {
    public function up(): void
    {
        $conn = config('journey_builder.connection', 'sqlsrv');
        $db = DB::connection($conn);
        $schema = Schema::connection($conn);
        if ($db->getDriverName() === 'sqlsrv') {
            if (!$schema->hasTable('jb_email_providers')) {
                $db->statement("CREATE TABLE dbo.jb_email_providers (
                    id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    name NVARCHAR(120) NOT NULL, smtp_host VARCHAR(255) NOT NULL,
                    smtp_port INT NOT NULL, smtp_username VARCHAR(255) NOT NULL,
                    smtp_secret VARCHAR(MAX) NOT NULL, from_email VARCHAR(254) NOT NULL,
                    from_name NVARCHAR(120) NOT NULL, smtp_security VARCHAR(12) NOT NULL,
                    pop3_host VARCHAR(255) NULL, pop3_port INT NULL,
                    pop3_username VARCHAR(255) NULL, pop3_secret VARCHAR(MAX) NULL,
                    pop3_security VARCHAR(12) NULL,
                    max_per_minute INT NOT NULL DEFAULT(60),
                    is_enabled BIT NOT NULL DEFAULT(0), is_default BIT NOT NULL DEFAULT(0),
                    deleted_at DATETIME2(3) NULL, created_at DATETIME2(3) NOT NULL,
                    updated_at DATETIME2(3) NOT NULL
                )");
                $db->statement('CREATE UNIQUE INDEX uq_jb_email_default ON dbo.jb_email_providers(is_default) WHERE is_default=1 AND deleted_at IS NULL');
            }
            if (!$schema->hasTable('jb_push_providers')) {
                $db->statement("CREATE TABLE dbo.jb_push_providers (
                    id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    name NVARCHAR(120) NOT NULL, website_id VARCHAR(64) NOT NULL,
                    api_secret VARCHAR(MAX) NOT NULL,
                    auth_mode VARCHAR(24) NOT NULL DEFAULT('bearer'),
                    is_enabled BIT NOT NULL DEFAULT(0), is_default BIT NOT NULL DEFAULT(0),
                    deleted_at DATETIME2(3) NULL, created_at DATETIME2(3) NOT NULL,
                    updated_at DATETIME2(3) NOT NULL
                )");
                $db->statement('CREATE UNIQUE INDEX uq_jb_push_default ON dbo.jb_push_providers(is_default) WHERE is_default=1 AND deleted_at IS NULL');
            }
            if (!$schema->hasTable('jb_push_subscriptions')) {
                $db->statement("CREATE TABLE dbo.jb_push_subscriptions (
                    id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    provider_id BIGINT NOT NULL, user_id VARCHAR(100) NOT NULL,
                    token_digest CHAR(64) NOT NULL, token_secret VARCHAR(MAX) NOT NULL,
                    is_enabled BIT NOT NULL DEFAULT(1),
                    created_at DATETIME2(3) NOT NULL, updated_at DATETIME2(3) NOT NULL
                )");
                $db->statement('CREATE UNIQUE INDEX uq_jb_push_sub_token ON dbo.jb_push_subscriptions(provider_id,token_digest)');
                $db->statement('CREATE INDEX idx_jb_push_sub_user ON dbo.jb_push_subscriptions(provider_id,user_id,is_enabled)');
            }
            if (!$schema->hasTable('jb_channel_dispatches')) {
                $db->statement("CREATE TABLE dbo.jb_channel_dispatches (
                    id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    flow_id BIGINT NOT NULL, run_id BIGINT NOT NULL,
                    node_id VARCHAR(80) NOT NULL, user_id VARCHAR(100) NOT NULL,
                    channel VARCHAR(12) NOT NULL, provider_id BIGINT NOT NULL,
                    recipient VARCHAR(320) NULL, payload NVARCHAR(MAX) NOT NULL,
                    status VARCHAR(20) NOT NULL, attempts SMALLINT NOT NULL DEFAULT(0),
                    last_error NVARCHAR(2000) NULL, provider_response NVARCHAR(2000) NULL,
                    available_at DATETIME2(3) NOT NULL, locked_at DATETIME2(3) NULL,
                    sent_at DATETIME2(3) NULL, created_at DATETIME2(3) NOT NULL,
                    updated_at DATETIME2(3) NOT NULL
                )");
                $db->statement('CREATE UNIQUE INDEX uq_jb_channel_run_node ON dbo.jb_channel_dispatches(run_id,node_id,channel)');
                $db->statement('CREATE INDEX idx_jb_channel_ready ON dbo.jb_channel_dispatches(channel,status,available_at,id)');
                $db->statement('CREATE INDEX idx_jb_channel_provider_rate ON dbo.jb_channel_dispatches(channel,provider_id,updated_at)');
                $db->statement('CREATE INDEX idx_jb_channel_flow ON dbo.jb_channel_dispatches(flow_id,status)');
            }
            if ($schema->hasTable('jb_flow_totals')) {
                foreach (['email_sent','email_failed','email_unknown','webpush_sent','webpush_failed','webpush_unknown'] as $column) {
                    if (!$schema->hasColumn('jb_flow_totals',$column))
                        $db->statement('ALTER TABLE dbo.jb_flow_totals ADD ['.$column.'] BIGINT NOT NULL DEFAULT(0)');
                }
            }
            return;
        }
        if (!$schema->hasTable('jb_email_providers')) $schema->create('jb_email_providers', static function (Blueprint $t) {
            $t->id(); $t->string('name',120); $t->string('smtp_host'); $t->integer('smtp_port');
            $t->string('smtp_username'); $t->text('smtp_secret'); $t->string('from_email',254);
            $t->string('from_name',120); $t->string('smtp_security',12);
            $t->string('pop3_host')->nullable(); $t->integer('pop3_port')->nullable();
            $t->string('pop3_username')->nullable(); $t->text('pop3_secret')->nullable();
            $t->string('pop3_security',12)->nullable(); $t->integer('max_per_minute')->default(60);
            $t->boolean('is_enabled')->default(false); $t->boolean('is_default')->default(false);
            $t->dateTime('deleted_at',3)->nullable(); $t->timestamps(3);
        });
        if (!$schema->hasTable('jb_push_providers')) $schema->create('jb_push_providers', static function (Blueprint $t) {
            $t->id(); $t->string('name',120); $t->string('website_id',64); $t->text('api_secret');
            $t->string('auth_mode',24)->default('bearer');
            $t->boolean('is_enabled')->default(false); $t->boolean('is_default')->default(false);
            $t->dateTime('deleted_at',3)->nullable(); $t->timestamps(3);
        });
        if (!$schema->hasTable('jb_push_subscriptions')) $schema->create('jb_push_subscriptions', static function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('provider_id'); $t->string('user_id',100);
            $t->char('token_digest',64); $t->text('token_secret'); $t->boolean('is_enabled')->default(true);
            $t->timestamps(3); $t->unique(['provider_id','token_digest']); $t->index(['provider_id','user_id','is_enabled']);
        });
        if (!$schema->hasTable('jb_channel_dispatches')) $schema->create('jb_channel_dispatches', static function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('flow_id'); $t->unsignedBigInteger('run_id');
            $t->string('node_id',80); $t->string('user_id',100); $t->string('channel',12);
            $t->unsignedBigInteger('provider_id'); $t->string('recipient',320)->nullable();
            $t->text('payload'); $t->string('status',20); $t->unsignedSmallInteger('attempts')->default(0);
            $t->text('last_error')->nullable(); $t->text('provider_response')->nullable();
            $t->dateTime('available_at',3); $t->dateTime('locked_at',3)->nullable();
            $t->dateTime('sent_at',3)->nullable(); $t->timestamps(3);
            $t->unique(['run_id','node_id','channel']); $t->index(['channel','status','available_at','id']);
            $t->index(['channel','provider_id','updated_at']); $t->index(['flow_id','status']);
        });
        if ($schema->hasTable('jb_flow_totals')) {
            foreach (['email_sent','email_failed','email_unknown','webpush_sent','webpush_failed','webpush_unknown'] as $column)
                if (!$schema->hasColumn('jb_flow_totals',$column))
                    $schema->table('jb_flow_totals',fn(Blueprint $t)=>$t->unsignedBigInteger($column)->default(0));
        }
    }
    public function down(): void {} // Never silently destroy campaign histories.
};
