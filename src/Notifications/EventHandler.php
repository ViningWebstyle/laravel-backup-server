<?php

namespace Spatie\BackupServer\Notifications;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Spatie\BackupServer\Exceptions\NotificationCouldNotBeSent;
use Spatie\BackupServer\Tasks\Backup\Events\BackupCompletedEvent;
use Spatie\BackupServer\Tasks\Backup\Events\BackupFailedEvent;
use Spatie\BackupServer\Tasks\Cleanup\Events\CleanupForDestinationCompletedEvent;
use Spatie\BackupServer\Tasks\Cleanup\Events\CleanupForDestinationFailedEvent;
use Spatie\BackupServer\Tasks\Cleanup\Events\CleanupForSourceCompletedEvent;
use Spatie\BackupServer\Tasks\Cleanup\Events\CleanupForSourceFailedEvent;
use Spatie\BackupServer\Tasks\Monitor\Events\HealthyDestinationFoundEvent;
use Spatie\BackupServer\Tasks\Monitor\Events\HealthySourceFoundEvent;
use Spatie\BackupServer\Tasks\Monitor\Events\UnhealthyDestinationFoundEvent;
use Spatie\BackupServer\Tasks\Monitor\Events\UnhealthySourceFoundEvent;

class EventHandler
{
    protected Repository $config;

    public function __construct(Repository $config)
    {
        $this->config = $config;
    }

    public function subscribe(Dispatcher $events)
    {
        $events->listen($this->allBackupEventClasses(), function ($event) {
            $notifiable = $this->determineNotifiable();

            $notification = $this->determineNotification($event);

            $notifiable->notify($notification);
        });
    }

    protected function determineNotifiable()
    {
        $notifiableClass = $this->config->get('backup-server.notifications.notifiable');

        return app($notifiableClass);
    }

    protected function determineNotification(object $event): Notification
    {
        $eventName = class_basename($event);

        $notificationClassName = Str::replaceLast('Event', 'Notification', $eventName);

        $notificationClass = collect($this->config->get('backup-server.notifications.notifications'))
            ->keys()
            ->first(fn ($notificationClass) => class_basename($notificationClass) === $notificationClassName);

        if (! $notificationClass) {
            throw NotificationCouldNotBeSent::noNotificationClassForEvent($event);
        }

        return new $notificationClass($event);
    }

    protected function allBackupEventClasses(): array
    {
        return [
            BackupCompletedEvent::class,
            BackupFailedEvent::class,
            CleanupForSourceCompletedEvent::class,
            CleanupForSourceFailedEvent::class,
            CleanupForDestinationCompletedEvent::class,
            CleanupForDestinationFailedEvent::class,
            HealthySourceFoundEvent::class,
            UnhealthySourceFoundEvent::class,
            HealthyDestinationFoundEvent::class,
            UnhealthyDestinationFoundEvent::class,
        ];
    }
}
