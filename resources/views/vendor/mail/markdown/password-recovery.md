@component('mail::message')
# Password Reset Request

Hi,

We received a request to reset the password for your Hours Ledger account. If you made this request, please use the code below to reset your password:

@component('mail::button', ['url' => ''])
Verification Code: **{{ $token }}**
@endcomponent

Simply enter this 6-digit code in the password recovery form. This code will expire in 30 minutes.

**For your security:**
- Never share this code with anyone
- Hours Ledger staff will never ask for this code
- If you didn't request a password reset, please ignore this email

If you have any questions, please contact our support team.

Best regards, <br>
**{{ config('application.mail.footer_name') }}**

@endcomponent
