<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Additive and non-destructive. Existing flows remain automatic. */
return new class extends Migration {
    public function up(): void
    {
        $name = config('journey_builder.connection', 'sqlsrv');
        $db = DB::connection($name);
        $schema = Schema::connection($name);
        if (!$schema->hasTable('jb_flows')) {
            throw new \RuntimeException('The base Journey Builder migration must be installed first.');
        }
        if ($schema->hasColumn('jb_flows', 'enrollment_mode')) return;
        if ($db->getDriverName() === 'sqlsrv') {
            $db->statement("ALTER TABLE [dbo].[jb_flows] ADD [enrollment_mode] VARCHAR(12) NOT NULL CONSTRAINT [df_jb_flows_enrollment_mode] DEFAULT('automatic')");
            return;
        }
        $schema->table('jb_flows', function (Blueprint $t) {
            $t->string('enrollment_mode',12)->default('automatic');
        });
    }
    public function down(): void {} // Never remove existing production flow configuration.
};
