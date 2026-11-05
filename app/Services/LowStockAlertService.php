<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationTypeEnum;
use App\Models\Notification;
use App\Models\Part;

/**
 * Detects parts with low stock and triggers in-app notifications.
 *
 * Used by the scheduled CheckLowStockJob to avoid calculation on every request.
 */
final class LowStockAlertService
{
    public function __construct(
        private readonly NotificationCreatorService $notificationCreatorService,
    ) {}

    /**
     * Returns parts in alert state, sorted by criticality
     * (current_stock / min_stock ratio).
     *
     * @return array<int, Part>
     */
    public function partsInAlert(): array
    {
        return Part::query()
            ->with(['category', 'taxRate'])
            ->lowStock()
            ->orderByRaw('CASE WHEN min_stock = 0 THEN 0 ELSE current_stock * 1.0 / min_stock END ASC')
            ->get()
            ->all();
    }

    /**
     * Creates an in-app notification for each alert part to all admins.
     *
     * The job runs daily, so an alert already sent for the same part at the same
     * stock level is skipped: admins are re-notified only when the situation changes.
     *
     * @return int number of notifications created
     */
    public function notifyAdminsForLowStock(): int
    {
        $parts = $this->partsInAlert();

        if ($parts === []) {
            return 0;
        }

        $configuredCooldown = config('services.stock.low_stock_alert_cooldown_hours', 24);
        $cooldownHours = max(1, is_numeric($configuredCooldown) ? (int) $configuredCooldown : 24);

        $recentlyNotified = Notification::query()
            ->where('type', NotificationTypeEnum::LowStock->value)
            ->where('created_at', '>=', now()->subHours($cooldownHours))
            ->get(['link', 'message'])
            ->map(fn (Notification $notification): string => $notification->link.'|'.$notification->message)
            ->flip();

        $entries = [];

        foreach ($parts as $part) {
            $message = __('stock.:part — stock atual :current (mínimo :min)', [
                'part' => $part->name,
                'current' => $part->current_stock,
                'min' => $part->min_stock,
            ]);
            $link = route('ui.stock.parts.show', $part);

            if ($recentlyNotified->has($link.'|'.$message)) {
                continue;
            }

            $entries[] = [
                'title' => NotificationTypeEnum::LowStock->label(),
                'message' => $message,
                'type' => NotificationTypeEnum::LowStock->value,
                'link' => $link,
            ];
        }

        if ($entries === []) {
            return 0;
        }

        return $this->notificationCreatorService->createForAdminsMany($entries);
    }
}
