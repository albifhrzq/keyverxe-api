<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('switch_asset_profile')->nullable()->after('is_homepage_featured');
        });

        DB::table('products')
            ->where('slug', 'crimson-linear-switch')
            ->update(['switch_asset_profile' => 'red']);

        DB::table('products')
            ->where('slug', 'nebula-tactile-switch')
            ->update(['switch_asset_profile' => 'brown']);

        DB::table('products')
            ->where('slug', 'frost-silent-linear')
            ->update(['switch_asset_profile' => 'black']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('switch_asset_profile');
        });
    }
};
