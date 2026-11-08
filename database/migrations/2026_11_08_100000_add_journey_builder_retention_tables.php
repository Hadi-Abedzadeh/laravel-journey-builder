<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Additive migration: never deletes or changes existing jb_* user records. */
return new class extends Migration {
    public function up(): void
    {
        $connection = config('journey_builder.connection', 'sqlsrv');
        $db = DB::connection($connection);
        $schema = Schema::connection($connection);
        if ($db->getDriverName() === 'sqlsrv') {
            if (!$schema->hasTable('jb_run_hashes')) {
                $db->statement('CREATE TABLE [dbo].[jb_run_hashes] ([flow_id] BIGINT NOT NULL, [user_digest] VARCHAR(64) NOT NULL, [created_at] DATETIME2(3) NOT NULL, CONSTRAINT [pk_jb_run_hashes] PRIMARY KEY ([flow_id],[user_digest]))');
            }
            if (!$schema->hasTable('jb_flow_totals')) {
                $db->statement('CREATE TABLE [dbo].[jb_flow_totals] ([flow_id] BIGINT NOT NULL PRIMARY KEY, [pruned_runs] BIGINT NOT NULL DEFAULT 0, [sms_sent] BIGINT NOT NULL DEFAULT 0, [sms_failed] BIGINT NOT NULL DEFAULT 0, [sms_unknown] BIGINT NOT NULL DEFAULT 0, [api_sent] BIGINT NOT NULL DEFAULT 0, [api_failed] BIGINT NOT NULL DEFAULT 0, [api_unknown] BIGINT NOT NULL DEFAULT 0, [updated_at] DATETIME2(3) NOT NULL)');
            }
            if (!$schema->hasTable('jb_node_totals')) {
                $db->statement('CREATE TABLE [dbo].[jb_node_totals] ([flow_id] BIGINT NOT NULL, [node_id] VARCHAR(80) NOT NULL, [arrivals] BIGINT NOT NULL DEFAULT 0, CONSTRAINT [pk_jb_node_totals] PRIMARY KEY ([flow_id],[node_id]))');
            }
            return;
        }
        if (!$schema->hasTable('jb_run_hashes')) $schema->create('jb_run_hashes', function(Blueprint $t) {
            $t->bigInteger('flow_id'); $t->string('user_digest',64); $t->dateTime('created_at',3);
            $t->primary(['flow_id','user_digest'],'pk_jb_run_hashes');
        });
        if (!$schema->hasTable('jb_flow_totals')) $schema->create('jb_flow_totals', function(Blueprint $t) {
            $t->bigInteger('flow_id')->primary(); $t->bigInteger('pruned_runs')->default(0);
            foreach(['sms_sent','sms_failed','sms_unknown','api_sent','api_failed','api_unknown'] as $key) $t->bigInteger($key)->default(0);
            $t->dateTime('updated_at',3);
        });
        if (!$schema->hasTable('jb_node_totals')) $schema->create('jb_node_totals', function(Blueprint $t) {
            $t->bigInteger('flow_id'); $t->string('node_id',80); $t->bigInteger('arrivals')->default(0);
            $t->primary(['flow_id','node_id'],'pk_jb_node_totals');
        });
    }
    public function down(): void {} // Do not erase production aggregates via rollback.
};
