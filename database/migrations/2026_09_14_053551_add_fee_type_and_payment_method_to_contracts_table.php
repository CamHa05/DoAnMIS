<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('agreed_fee_type', 20)
                ->nullable()
                ->after('agreed_fee');

            $table->string('payment_method', 20)
                ->nullable()
                ->after('agreed_fee_type');
        });

        /*
         * Backfill dữ liệu contract cũ:
         * agreed_fee_type lấy từ fee_type của request tương ứng.
         */
        DB::table('contracts')
            ->join(
                'tutoring_requests',
                'contracts.request_id',
                '=',
                'tutoring_requests.request_id'
            )
            ->update([
                'contracts.agreed_fee_type' => DB::raw(
                    'tutoring_requests.fee_type'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn([
                'agreed_fee_type',
                'payment_method',
            ]);
        });
    }
};