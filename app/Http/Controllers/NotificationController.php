<?php

namespace App\Http\Controllers;

use App\Support\NotificationPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Display a listing of the user's notifications.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = $user->notifications();

        // Aplicar filtros
        if ($request->filled('filter')) {
            if ($request->filter === 'unread') {
                $query->whereNull('read_at');
            } elseif ($request->filter === 'read') {
                $query->whereNotNull('read_at');
            }
        }

        $notifications = $query->paginate(20);
        $unreadCount = $user->unreadNotifications()->count();
        $presentedNotifications = $notifications->getCollection()
            ->mapWithKeys(fn ($notification): array => [$notification->id => NotificationPresenter::present($notification)]);

        return view('notifications.index', compact('notifications', 'unreadCount', 'presentedNotifications'));
    }

    /**
     * Get unread notifications (for AJAX/API).
     */
    public function unread()
    {
        $user = Auth::user();

        $notifications = $user->unreadNotifications()
            ->take(10)
            ->get()
            ->map(function ($notification) {
                return NotificationPresenter::present($notification);
            });

        return response()->json([
            'notifications' => $notifications,
            'count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead($id)
    {
        $user = Auth::user();

        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Notificación marcada como leída',
            ]);
        }

        return back()->with('success', 'Notificación marcada como leída');
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Todas las notificaciones marcadas como leídas',
            ]);
        }

        return back()->with('success', 'Todas las notificaciones marcadas como leídas');
    }

    /**
     * Remove the specified notification.
     */
    public function destroy($id)
    {
        $user = Auth::user();

        $notification = $user->notifications()->findOrFail($id);
        $notification->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Notificación eliminada',
            ]);
        }

        return back()->with('success', 'Notificación eliminada');
    }

    /**
     * Clear all read notifications.
     */
    public function clearRead()
    {
        $user = Auth::user();
        $user->readNotifications()->delete();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Notificaciones leídas eliminadas',
            ]);
        }

        return back()->with('success', 'Notificaciones leídas eliminadas');
    }
}
