@component('mail::message')
# Welcome to Hour Ledger!

Hi {{ $name }},

Thank you for signing up! To complete your registration and start tracking your hours, please verify your email address using the code below:

@component('mail::button', ['url' => ''])
Verification Code: **{{ $token }}**
@endcomponent

Simply enter this 6-digit code in your registration form. This code will expire in 30 minutes.

If you didn't create this account, you can safely ignore this email.

Best regards, <br>
**{{ config('application.mail.footer_name') }}**

@endcomponent
