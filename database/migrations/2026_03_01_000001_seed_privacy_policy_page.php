<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Only seed if no privacy policy page exists — don't overwrite manually edited content
        Page::firstOrCreate(
            ['slug' => 'privacy-policy'],
            [
                'title' => 'Privacy Policy',
                'is_published' => true,
                'content' => <<<'HTML'
<h2>Privacy Policy</h2>
<p><strong>Last updated:</strong> March 1, 2026</p>

<p>Lighthouse Minecraft ("we", "us", or "our") is committed to protecting the privacy of our community members. This Privacy Policy explains what information we collect, how we use it, and your rights regarding your data.</p>

<h3>Information We Collect</h3>
<p>When you register for an account on the Lighthouse website, we collect the following information:</p>
<ul>
    <li><strong>Name</strong> &mdash; Your display name or nickname used within the community.</li>
    <li><strong>Email Address</strong> &mdash; Used for account authentication, notifications, and account recovery.</li>
    <li><strong>Date of Birth</strong> &mdash; Used to determine age-appropriate access and to comply with child safety regulations (COPPA). This information is stored securely and is <strong>never shared outside of Lighthouse</strong>, including with any third-party integrations or services.</li>
    <li><strong>Parent/Guardian Email Address</strong> (for users under 17) &mdash; Used to notify and enable parental oversight of minor accounts. This information is stored securely and is <strong>never shared outside of Lighthouse</strong>, including with any third-party integrations or services.</li>
    <li><strong>Password</strong> &mdash; Stored in a securely hashed format; we never store or have access to your plaintext password.</li>
</ul>

<h3>Linked Accounts</h3>
<p>You may optionally link the following third-party accounts:</p>
<ul>
    <li><strong>Minecraft Account</strong> &mdash; Your Minecraft username and UUID are stored to manage server whitelist access and in-game rank synchronization.</li>
    <li><strong>Discord Account</strong> &mdash; Your Discord user ID and username are stored to manage role synchronization with the community Discord server.</li>
</ul>

<h3>How We Use Your Information</h3>
<p>We use the information we collect for the following purposes:</p>
<ul>
    <li>To provide and maintain community services (website, Minecraft server, Discord).</li>
    <li>To authenticate your identity and manage your account.</li>
    <li>To enforce community rules and manage the Brig (discipline) system.</li>
    <li>To enable parental oversight for minor accounts in compliance with COPPA.</li>
    <li>To send notifications about your account, support tickets, and community updates.</li>
</ul>

<h3>Information Sharing</h3>
<p>We <strong>do not sell, trade, or share</strong> your personal information with third parties. Your date of birth and parent/guardian email address are <strong>never shared outside of Lighthouse</strong>, not even with third-party integrations such as Minecraft or Discord. The only information shared with third-party services is what is necessary to operate linked accounts (e.g., your Minecraft username for server whitelisting).</p>

<h3>Children's Privacy (COPPA Compliance)</h3>
<p>We take the privacy of children seriously. For users under the age of 13:</p>
<ul>
    <li>A parent or guardian email is required during registration.</li>
    <li>Account access requires explicit parental approval.</li>
    <li>Parents can manage their child's permissions (site access, Minecraft, Discord) through the Parent Portal.</li>
    <li>Parents can view and manage their child's linked accounts and support tickets.</li>
</ul>

<h3>Data Security</h3>
<p>We implement appropriate security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>

<h3>Your Rights</h3>
<p>You have the right to:</p>
<ul>
    <li>Access your personal information through your account settings.</li>
    <li>Update your information at any time.</li>
    <li>Request deletion of your account by contacting staff via a support ticket.</li>
</ul>

<h3>Contact Us</h3>
<p>If you have any questions about this Privacy Policy, please reach out via the support ticket system on the website.</p>
HTML,
            ]
        );
    }

    public function down(): void
    {
        Page::where('slug', 'privacy-policy')->delete();
    }
};
