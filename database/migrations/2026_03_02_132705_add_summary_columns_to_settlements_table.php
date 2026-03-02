<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            if (!Schema::hasColumn('settlements', 'initial_total')) {
                $table->decimal('initial_total', 15, 2)->default(0);
            }
            
            if (!Schema::hasColumn('settlements', 'total_value')) {
                $table->decimal('total_value', 15, 2)->default(0);
            }
            
            if (!Schema::hasColumn('settlements', 'total_expenses')) {
                $table->decimal('total_expenses', 15, 2)->default(0);
            }
            
            if (!Schema::hasColumn('settlements', 'expense_percentage')) {
                $table->decimal('expense_percentage', 10, 3)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropColumn([
                'initial_total', 
                'total_value', 
                'total_expenses', 
                'expense_percentage'
            ]);
        });
    }
};