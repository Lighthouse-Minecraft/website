<?php

use App\Models\SiteConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SiteConfig::setValue('max_background_check_document_size_kb', '10240');
        SiteConfig::query()->where('key', 'max_background_check_document_size_kb')->update([
            'description' => 'Maximum allowed size in kilobytes for background check document uploads (PDFs)',
        ]);
    }

    public function down(): void
    {
        SiteConfig::query()->where('key', 'max_background_check_document_size_kb')->delete();
    }
};
