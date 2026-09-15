<?php

use App\Models\Outlet;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Outlet::where('code', 'PUSAT')->update(['is_sales_enabled' => false]);
    }

    public function down(): void
    {
        Outlet::where('code', 'PUSAT')->update(['is_sales_enabled' => true]);
    }
};
