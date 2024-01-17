<?php

namespace App\Notifications;

use App\Enums\NotificationFrequency;
use App\Models\GeneralSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use stdClass;

class TaskNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    protected ?stdClass $notificationData = null;

    protected array $broadcastMessage = [];

    public function __construct($notificationData, $broadcastMessage = [])
    {
        $this->notificationData = $notificationData;
        $this->broadcastMessage = $broadcastMessage;
    }

    public function toBroadcast(): BroadcastMessage
    {
        return new BroadcastMessage([
            'message' => $this->broadcastMessage
        ]);
    }

    /**
     * @return string[]
     */
    public function via($user): array
    {
        $channels = ['database'];

        $typeSettings = $user->notificationSettings()
            ->where('type', $this->notificationData->type)
            ->first();

        if ($typeSettings?->enabled_email && $typeSettings?->frequency === NotificationFrequency::IMMEDIATELY) {
            $channels[] = 'mail';
        }

        if ($typeSettings?->enabled_push && !empty($this->broadcastMessage)) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    public function toMail(): MailMessage
    {
        $settings = app(GeneralSettings::class);
        return (new MailMessage())
            ->from(
                $settings->business_email !== '' ? $settings->business_email : 'noreply@artwork.software',
                'Artwork'
            )
            ->subject($this->notificationData->title)
            ->markdown('emails.simple-mail', ['notification' => $this->notificationData]);
    }

    public function toArray(): stdClass
    {
        return $this->notificationData;
    }
}
