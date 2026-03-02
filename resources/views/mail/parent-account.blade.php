<x-mail::message>
    @if($requiresApproval)
        {{ $childName }} has requested an account on Lighthouse MC, a Christian Minecraft community for youth.

        We strive to have a safe and positive environment for all our members, especially our younger players. As part of our commitment to safety, we require parental approval for accounts created by users under the age of 13.

        As a parent you will have access to the Parent Portal where you can manage your child's permissions, view their linked Minecraft and Discord accounts, and monitor their activity. Please review the information and create your own account to approve or deny this request.
    @else
        {{ $childName }} has created an account on Lighthouse MC, a Christian Minecraft community for youth.

        As their parent or guardian, you can create your own account to manage their permissions, view their linked Minecraft and Discord accounts, and monitor their activity through the Parent Portal.
    @endif

    At any time you may contact the staff team if you have questions or concerns about your child's account or our community guidelines.

    <x-mail::button :url="$registerUrl">
        Create Your Account
    </x-mail::button>
</x-mail::message>
