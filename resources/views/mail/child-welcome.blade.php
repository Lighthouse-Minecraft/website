<x-mail::message>
# Welcome to Lighthouse, {{ $childName }}!

Your parent {{ $parentName }} has created an account for you on Lighthouse. We're glad to have you!

To gain access to your account, you will need to use the password reset feature to set your password.

<x-mail::button :url="$resetUrl">
Reset Your Password
</x-mail::button>
</x-mail::message>
