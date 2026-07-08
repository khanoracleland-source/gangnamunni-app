<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gangnamunni_ad_slot_products')) {
            Schema::table('gangnamunni_ad_slot_products', function (Blueprint $table) {
                if (! Schema::hasColumn('gangnamunni_ad_slot_products', 'billing_model')) {
                    $table->string('billing_model', 20)->default('monthly')->after('monthly_price');
                }
                if (! Schema::hasColumn('gangnamunni_ad_slot_products', 'cpc_price')) {
                    $table->decimal('cpc_price', 12, 2)->default(0)->after('billing_model');
                }
                if (! Schema::hasColumn('gangnamunni_ad_slot_products', 'cpm_price')) {
                    $table->decimal('cpm_price', 12, 2)->default(0)->after('cpc_price');
                }
                if (! Schema::hasColumn('gangnamunni_ad_slot_products', 'daily_budget')) {
                    $table->decimal('daily_budget', 12, 2)->nullable()->after('cpm_price');
                }
                if (! Schema::hasColumn('gangnamunni_ad_slot_products', 'total_budget')) {
                    $table->decimal('total_budget', 14, 2)->nullable()->after('daily_budget');
                }
            });
        }

        if (Schema::hasTable('gangnamunni_ad_slot_bookings')) {
            Schema::table('gangnamunni_ad_slot_bookings', function (Blueprint $table) {
                if (! Schema::hasColumn('gangnamunni_ad_slot_bookings', 'daily_spent')) {
                    $table->decimal('daily_spent', 12, 2)->default(0)->after('assigned_by');
                }
                if (! Schema::hasColumn('gangnamunni_ad_slot_bookings', 'total_spent')) {
                    $table->decimal('total_spent', 14, 2)->default(0)->after('daily_spent');
                }
                if (! Schema::hasColumn('gangnamunni_ad_slot_bookings', 'budget_date')) {
                    $table->date('budget_date')->nullable()->after('total_spent');
                }
                if (! Schema::hasColumn('gangnamunni_ad_slot_bookings', 'last_charged_at')) {
                    $table->dateTime('last_charged_at')->nullable()->after('budget_date');
                }
                if (! Schema::hasColumn('gangnamunni_ad_slot_bookings', 'pause_reason')) {
                    $table->string('pause_reason', 100)->nullable()->after('last_charged_at');
                }
            });
        }

        if (! Schema::hasTable('gangnamunni_ad_slot_events')) {
            Schema::create('gangnamunni_ad_slot_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('event_key', 64)->unique();
                $table->string('event_type', 20);
                $table->unsignedInteger('booking_id');
                $table->unsignedInteger('product_id');
                $table->unsignedInteger('hospital_id');
                $table->unsignedInteger('treatment_id')->nullable();
                $table->string('keyword', 200)->nullable();
                $table->unsignedTinyInteger('rank_position')->nullable();
                $table->string('billing_model', 20);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('charge_amount', 12, 2)->default(0);
                $table->boolean('is_billable')->default(false);
                $table->boolean('is_charged')->default(false);
                $table->string('skip_reason', 50)->nullable();
                $table->text('page_url')->nullable();
                $table->text('target_url')->nullable();
                $table->char('visitor_ip_hash', 64)->nullable();
                $table->char('visitor_id_hash', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->dateTime('created_at')->useCurrent();

                $table->index(['booking_id', 'event_type', 'created_at'], 'idx_slot_event_booking');
                $table->index(['hospital_id', 'created_at'], 'idx_slot_event_hospital');
                $table->index(['keyword', 'created_at'], 'idx_slot_event_keyword');
            });
        }

        if (Schema::hasTable('gangnamunni_cpv_settings')) {
            DB::table('gangnamunni_cpv_settings')->updateOrInsert(
                ['setting_key' => 'ad_performance_test_mode'],
                [
                    'setting_value' => '1',
                    'setting_type' => 'toggle',
                    'category' => 'advertising',
                    'sub_category' => 'performance',
                    'label' => '검색 광고 테스트 모드',
                    'description' => '활성화 시 CPC/CPM 이벤트는 기록하지만 지갑에서 차감하지 않습니다.',
                    'sort_order' => 1,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gangnamunni_ad_slot_events');

        if (Schema::hasTable('gangnamunni_ad_slot_bookings')) {
            $columns = array_values(array_filter(
                ['daily_spent', 'total_spent', 'budget_date', 'last_charged_at', 'pause_reason'],
                fn (string $column) => Schema::hasColumn('gangnamunni_ad_slot_bookings', $column)
            ));
            if ($columns) {
                Schema::table('gangnamunni_ad_slot_bookings', fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }

        if (Schema::hasTable('gangnamunni_ad_slot_products')) {
            $columns = array_values(array_filter(
                ['billing_model', 'cpc_price', 'cpm_price', 'daily_budget', 'total_budget'],
                fn (string $column) => Schema::hasColumn('gangnamunni_ad_slot_products', $column)
            ));
            if ($columns) {
                Schema::table('gangnamunni_ad_slot_products', fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }

        if (Schema::hasTable('gangnamunni_cpv_settings')) {
            DB::table('gangnamunni_cpv_settings')
                ->where('setting_key', 'ad_performance_test_mode')
                ->delete();
        }
    }
};
