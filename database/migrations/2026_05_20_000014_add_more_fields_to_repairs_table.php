<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('repairs', function (Blueprint $table) {

            $table->string(
                'contact_phone'
            )->nullable();

            $table->string(
                'screen_password'
            )->nullable();

            $table->string(
                'account_type'
            )->nullable();

            $table->string(
                'account_email'
            )->nullable();

            $table->string(
                'account_password'
            )->nullable();

        });
    }

    public function down(): void
    {
        Schema::table('repairs', function (Blueprint $table) {

            $table->dropColumn([

                'contact_phone',

                'screen_password',

                'account_type',

                'account_email',

                'account_password',

            ]);
        });
    }
};
