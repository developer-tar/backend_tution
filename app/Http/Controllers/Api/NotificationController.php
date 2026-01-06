<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get unread notification count for the authenticated user
     *
     * @return JsonResponse
     */
    public function getUnreadCount(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                    'data' => ['count' => 0],
                ], 401);
            }

            $unreadCount = $user->unreadNotifications()->count();

            return response()->json([
                'success' => true,
                'message' => 'Unread notification count fetched successfully.',
                'data' => ['count' => $unreadCount],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unread notification count.',
                'data' => ['count' => 0],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get all notifications for the authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                    'data' => [],
                ], 401);
            }

            $perPage = $request->input('per_page', 15);
            $notifications = $user->notifications()->paginate($perPage);

            $transformedNotifications = $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at->format('Y-m-d H:i:s'),
                    'created_at_human' => $notification->created_at->diffForHumans(),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Notifications fetched successfully.',
                'data' => $transformedNotifications,
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'unread_count' => $user->unreadNotifications()->count(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return errorLog("Failed to fetch notifications: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Mark a notification as read
     *
     * @param string $id
     * @return JsonResponse
     */
    public function markAsRead($id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            $notification = $user->notifications()->where('id', $id)->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found.',
                ], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read.',
            ], 200);
        } catch (\Exception $e) {
            return errorLog("Failed to mark notification as read: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }

    /**
     * Mark all notifications as read
     *
     * @return JsonResponse
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ], 401);
            }

            $user->unreadNotifications->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read.',
            ], 200);
        } catch (\Exception $e) {
            return errorLog("Failed to mark all notifications as read: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }
}
