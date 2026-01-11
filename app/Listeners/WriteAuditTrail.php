<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Http\Middleware\Impersonate;
use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * WriteAuditTrail Listener
 *
 * Writes audit log entries for domain events.
 * Implements ShouldQueue for non-blocking audit operations.
 *
 * SECURITY: Properly tracks impersonation context by recording both
 * the actual performer (performed_by_id) and the impersonated user (impersonating_as_id).
 */
class WriteAuditTrail implements ShouldQueue
{
    public function handle(object $event): void
    {
        // Generic fallback writer for domain events.
        try {
            $req = request();
            $user = auth()->user();
            $userId = $user?->getKey();

            // SECURITY FIX: Track impersonation context
            // If impersonation is active, record both the actual performer and impersonated user
            $isImpersonating = Impersonate::isImpersonating();
            $performedById = $isImpersonating
                ? Impersonate::getActualPerformerId()
                : $userId;
            $impersonatingAsId = $isImpersonating
                ? Impersonate::getImpersonatedUserId()
                : null;

            // Build meta array consistently
            $meta = [];
            if ($impersonatingAsId !== null) {
                $meta['impersonation_session'] = true;
            }
            // Allow events to provide additional meta
            if (method_exists($event, 'meta')) {
                $meta = array_merge($meta, (array) $event->meta());
            }

            AuditLog::create([
                'user_id' => $userId,
                'performed_by_id' => $performedById,
                'impersonating_as_id' => $impersonatingAsId,
                'action' => class_basename($event),
                'subject_type' => method_exists($event, 'subjectType') ? $event->subjectType() : null,
                'subject_id' => method_exists($event, 'subjectId') ? $event->subjectId() : null,
                'ip' => $req?->ip(),
                'user_agent' => (string) $req?->userAgent(),
                'old_values' => method_exists($event, 'old') ? (array) $event->old() : [],
                'new_values' => method_exists($event, 'new') ? (array) $event->new() : [],
                'meta' => ! empty($meta) ? $meta : null,
            ]);
        } catch (\Throwable) {
            // swallow errors
        }
    }
}
