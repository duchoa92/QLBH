<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SECRET_COLUMNS = ['screen_password', 'screen_pattern', 'account_password'];

    public function up(): void
    {
        Schema::table('repairs', function (Blueprint $table): void {
            $table->string('screen_lock_type', 20)->nullable()->after('screen_pattern');

            foreach (self::SECRET_COLUMNS as $column) {
                $table->text($column)->nullable()->change();
            }
        });

        DB::table('repairs')->select(array_merge(['id'], self::SECRET_COLUMNS))
            ->orderBy('id')->chunkById(200, function ($repairs): void {
                foreach ($repairs as $repair) {
                    $changes = [];
                    foreach (self::SECRET_COLUMNS as $column) {
                        if ($repair->{$column} !== null && $repair->{$column} !== '') {
                            $changes[$column] = Crypt::encryptString($repair->{$column});
                        }
                    }
                    if ($changes !== []) DB::table('repairs')->where('id', $repair->id)->update($changes);
                }
            });
    }

    public function down(): void
    {
        DB::table('repairs')->select(array_merge(['id'], self::SECRET_COLUMNS))
            ->orderBy('id')->chunkById(200, function ($repairs): void {
                foreach ($repairs as $repair) {
                    $changes = [];
                    foreach (self::SECRET_COLUMNS as $column) {
                        if ($repair->{$column} !== null && $repair->{$column} !== '') {
                            $changes[$column] = Crypt::decryptString($repair->{$column});
                        }
                    }
                    if ($changes !== []) DB::table('repairs')->where('id', $repair->id)->update($changes);
                }
            });

        Schema::table('repairs', function (Blueprint $table): void {
            $table->dropColumn('screen_lock_type');
            foreach (['screen_password', 'account_password'] as $column) {
                $table->string($column)->nullable()->change();
            }
        });
    }
};
