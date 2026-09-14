<?php

use App\Models\Outlet;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('warehouses', 'outlet_id')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->foreignId('outlet_id')->nullable()->after('id')->constrained('outlets')->nullOnDelete();
            });
        }

        $pusat = Warehouse::where('code', 'PUSAT')->first();

        if (! $pusat) {
            return;
        }

        $outlet = Outlet::firstOrCreate(
            ['code' => 'PUSAT'],
            [
                'name' => $pusat->name,
                'is_active' => true,
                'is_sales_enabled' => true,
                'address' => $pusat->address,
                'phone' => $pusat->phone,
            ],
        );

        if (! $pusat->outlet_id) {
            $pusat->update(['outlet_id' => $outlet->id]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('warehouses', 'outlet_id')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->dropForeign(['outlet_id']);
                $table->dropColumn('outlet_id');
            });
        }
    }
};
