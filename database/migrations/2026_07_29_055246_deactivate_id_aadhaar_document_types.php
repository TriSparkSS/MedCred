<?php

use App\Models\DocumentType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DocumentType::firstOrCreate(
            ['name' => 'Government Photo ID'],
            ['is_active' => true]
        );

        DocumentType::whereIn('name', [
            'ID Proof',
            'ID AADHAR CARD',
            'ID Aadhaar Card',
            'Aadhaar Card',
            'Aadhar Card',
        ])->update(['is_active' => false]);
    }

    public function down(): void
    {
        DocumentType::where('name', 'ID Proof')->update(['is_active' => true]);
    }
};
