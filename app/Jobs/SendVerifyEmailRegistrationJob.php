<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\VerifyEmailRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

final class SendVerifyEmailRegistrationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $email,
        public readonly string $token,
        public readonly string $name,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /** @var \Illuminate\Mail\Mailable|\Illuminate\Contracts\Mail\Mailable $mail */
        $mail = new VerifyEmailRegistration(
            email: $this->email,
            token: $this->token,
            name: $this->name,
        );

        Mail::to($this->email)->send($mail);
    }
}
