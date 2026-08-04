<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Auth::user()->notifications();

        if ($request->query('status') === 'unread') {
            $query->whereNull('read_at');
        } elseif ($request->query('status') === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($request->filled('category')) {
            $query->where('data->category', $request->query('category'));
        }

        $notifications = $query->paginate($request->integer('per_page', 15));

        return $this->paginated(NotificationResource::collection($notifications));
    }

    public function unreadCount(): JsonResponse
    {
        return $this->success(['count' => Auth::user()->unreadNotifications()->count()]);
    }

    public function markRead(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->success(new NotificationResource($notification));
    }

    public function markAllRead(): JsonResponse
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(null, 'All notifications marked as read.');
    }
}
