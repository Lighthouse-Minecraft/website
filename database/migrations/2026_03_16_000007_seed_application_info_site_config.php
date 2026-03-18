<?php

use App\Models\SiteConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SiteConfig::setValue('application_info_page', <<<'MD'
## Before You Apply

Thank you for your interest in joining the Lighthouse staff team! Before you proceed, please review the following:

### Our Mission
Lighthouse exists to provide a safe, welcoming Minecraft community where players of all ages can grow, connect, and thrive.

### What We Expect From Staff
- A commitment to upholding our community values
- Active participation in your assigned department
- Professional and respectful conduct at all times
- Availability to fulfill the responsibilities of the position

### The Application Process
1. Submit your application with thoughtful, honest answers
2. Our team will review your application
3. If selected, you will be invited to an interview
4. A background check will be conducted
5. You will be notified of the final decision

By clicking **Continue**, you confirm that you have read and understand the above information.
MD);

        SiteConfig::query()->where('key', 'application_info_page')->update([
            'description' => 'Markdown content shown to applicants before they start a staff application',
        ]);
    }

    public function down(): void
    {
        SiteConfig::query()->where('key', 'application_info_page')->delete();
    }
};
