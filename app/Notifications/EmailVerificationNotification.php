<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class EmailVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $parentName = $notifiable->first_name . ' ' . $notifiable->last_name;

        return (new MailMessage)
            ->subject('Verify Your Email Address')
            ->greeting('Dear ' . $parentName . ',')
            ->line('Thank you so much for completing the enrolment form and paying the deposit for our 11 Plus course.')
            ->line('')
            ->line('We\'ll be in touch soon to let you know your child\'s class and time slot.')
            ->line('')
            ->line('Please click the button below to verify your email address:')
            ->action('Verify Email Address', $verificationUrl)
            ->line('')
            ->line('*This verification link will expire in 24 hours.*')
            ->line('')
            ->line('Thank you for choosing Eleven Plus Magic — we\'re really looking forward to supporting your child on their 11 Plus journey.')
            ->line('')
            ->salutation('Kind regards, 11PlusMagic Team');
    }

    /**
     * Get the verification URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        // Create a signed URL that expires in 24 hours
        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // Extract the path and query from the signed URL
        $parsedUrl = parse_url($signedUrl);
        $path = $parsedUrl['path'] ?? '';
        $query = $parsedUrl['query'] ?? '';

        // Return the frontend URL with the verification parameters
        return $frontendUrl . '/verify-email?' . $query;
    }
}
