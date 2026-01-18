<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EnrollmentConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $studentUsername;
    protected $studentPassword;
    protected $studentName;
    protected $courseName;
    protected $startDate;
    protected $endDate;

    /**
     * Create a new notification instance.
     */
    public function __construct($studentUsername, $studentPassword, $studentName, $courseName = null, $startDate = null, $endDate = null)
    {
        $this->studentUsername = $studentUsername;
        $this->studentPassword = $studentPassword;
        $this->studentName = $studentName;
        $this->courseName = $courseName ?? 'Year 3 Eleven Plus Course';
        $this->startDate = $startDate;
        $this->endDate = $endDate;
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
        // Format start date
        $startDateFormatted = 'Saturday 10th January 2026'; // Default fallback
        if ($this->startDate) {
            try {
                $startDateFormatted = \Carbon\Carbon::parse($this->startDate)->format('l jS F Y');
            } catch (\Exception $e) {
                // Keep default if parsing fails
            }
        }

        // Format end date for course materials availability
        $materialsDateFormatted = 'Saturday 10th January'; // Default fallback
        if ($this->startDate) {
            try {
                $materialsDateFormatted = \Carbon\Carbon::parse($this->startDate)->format('l jS F');
            } catch (\Exception $e) {
                // Keep default if parsing fails
            }
        }

        return (new MailMessage)
            ->subject('Welcome to ' . $this->courseName)
            ->greeting('Dear Parent of ' . $this->studentName . ',')
            ->line('We\'re so pleased to welcome ' . $this->studentName . ' to our ' . $this->courseName . ', starting on ' . $startDateFormatted . ' — we\'re really excited to have them with us!')
            ->line('')
            ->line('You\'ll be using our Virtual Learning Environment (VLE) to access lessons, homework, and important updates throughout the course.')
            ->line('')
            ->line('**VLE login:**')
            ->line('https://admin.11plusmagic.co.uk/login')
            ->line('')
            ->line('**' . $this->studentName . '\'s details:**')
            ->line('Username: ' . $this->studentUsername)
            ->line('Password: ' . $this->studentPassword)
            ->line('')
            ->line('Please remember to change the password after your first login by clicking the profile icon in the top-right corner.')
            ->line('')
            ->line('Course materials will be available from ' . $materialsDateFormatted . '. We recommend using Google Chrome for the best experience.')
            ->line('')
            ->line('If you need any help at all, we\'re always happy to support you.')
            ->line('')
            ->salutation('Warmest regards, 11PlusMagic Team');
    }
}
