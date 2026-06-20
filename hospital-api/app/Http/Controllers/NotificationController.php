<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Get notifications list for a user
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'type' => 'nullable|string',
            'is_read' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $query = DB::table('notifications')
            ->where('user_id', $request->user_id);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('is_read')) {
            $query->where('is_read', $request->is_read);
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->limit($request->get('limit', 20))
            ->get();

        // Get unread count
        $unreadCount = DB::table('notifications')
            ->where('user_id', $request->user_id)
            ->where('is_read', 0)
            ->count();

        return response()->json([
            'status' => true,
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
                'total' => $notifications->count()
            ]
        ]);
    }

    /**
     * Get notification detail
     */
    public function show($id)
    {
        $notification = DB::table('notifications')
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        // Mark as read
        DB::table('notifications')
            ->where('id', $id)
            ->update([
                'is_read' => 1,
                'read_at' => now()
            ]);

        return response()->json([
            'status' => true,
            'data' => $notification
        ]);
    }

    /**
     * Send notification to user
     */
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'type' => 'required|string|max:50',
            'title' => 'required|string|max:200',
            'body' => 'required|string',
            'data' => 'nullable|array',
            'image_url' => 'nullable|url|max:500',
            'priority' => 'nullable|in:high,normal,low',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        // Get user's FCM tokens
        $fcmTokens = DB::table('fcm_tokens')
            ->where('user_id', $request->user_id)
            ->where('is_active', 1)
            ->pluck('fcm_token')
            ->toArray();

        // Create notification record
        $notificationId = DB::table('notifications')->insertGetId([
            'user_id' => $request->user_id,
            'type' => $request->type,
            'title' => $request->title,
            'body' => $request->body,
            'data' => $request->has('data') ? json_encode($request->data) : null,
            'image_url' => $request->get('image_url'),
            'priority' => $request->get('priority', 'normal'),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $notification = DB::table('notifications')->where('id', $notificationId)->first();

        // Send FCM if tokens exist
        if (!empty($fcmTokens)) {
            foreach ($fcmTokens as $token) {
                $this->sendFCM($notificationId, $token, $request->all());
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification sent',
            'data' => $notification
        ], 201);
    }

    /**
     * Send notification using template
     */
    public function sendWithTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'template_type' => 'required|string',
            'variables' => 'nullable|array',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        // Get template
        $template = DB::table('notification_templates')
            ->where('type', $request->template_type)
            ->where('is_active', 1)
            ->first();

        if (!$template) {
            return response()->json([
                'status' => false,
                'message' => 'Template not found'
            ], 404);
        }

        // Replace variables in template
        $title = $template->title_template;
        $body = $template->body_template;
        $variables = $request->get('variables', []);

        foreach ($variables as $key => $value) {
            $title = str_replace('{' . $key . '}', $value, $title);
            $body = str_replace('{' . $key . '}', $value, $body);
        }

        // Get user's FCM tokens
        $fcmTokens = DB::table('fcm_tokens')
            ->where('user_id', $request->user_id)
            ->where('is_active', 1)
            ->pluck('fcm_token')
            ->toArray();

        // Create notification record
        $notificationId = DB::table('notifications')->insertGetId([
            'user_id' => $request->user_id,
            'type' => $request->template_type,
            'title' => $title,
            'body' => $body,
            'data' => $request->has('data') ? json_encode($request->data) : null,
            'priority' => 'normal',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $notification = DB::table('notifications')->where('id', $notificationId)->first();

        // Send FCM if tokens exist
        if (!empty($fcmTokens)) {
            foreach ($fcmTokens as $token) {
                $this->sendFCM($notificationId, $token, [
                    'title' => $title,
                    'body' => $body,
                    'data' => $request->get('data', []),
                    'priority' => 'normal'
                ]);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification sent',
            'data' => $notification
        ], 201);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $updated = DB::table('notifications')
            ->where('id', $id)
            ->where('user_id', $request->user_id)
            ->update([
                'is_read' => 1,
                'read_at' => now()
            ]);

        if (!$updated) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found or unauthorized'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        DB::table('notifications')
            ->where('user_id', $request->user_id)
            ->where('is_read', 0)
            ->update([
                'is_read' => 1,
                'read_at' => now()
            ]);

        return response()->json([
            'status' => true,
            'message' => 'All notifications marked as read'
        ]);
    }

    /**
     * Delete notification
     */
    public function destroy($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $deleted = DB::table('notifications')
            ->where('id', $id)
            ->where('user_id', $request->user_id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found or unauthorized'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Notification deleted'
        ]);
    }

    /**
     * Register FCM token
     */
    public function registerToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'device_id' => 'required|string|max:100',
            'fcm_token' => 'required|string|max:255',
            'platform' => 'required|in:android,ios,web',
            'app_version' => 'nullable|string|max:20',
            'os_version' => 'nullable|string|max:50',
            'device_model' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        // Upsert FCM token
        DB::table('fcm_tokens')->updateOrInsert(
            [
                'user_id' => $request->user_id,
                'device_id' => $request->device_id
            ],
            [
                'fcm_token' => $request->fcm_token,
                'platform' => $request->platform,
                'app_version' => $request->get('app_version'),
                'os_version' => $request->get('os_version'),
                'device_model' => $request->get('device_model'),
                'is_active' => 1,
                'last_used_at' => now(),
                'updated_at' => now()
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'FCM token registered'
        ], 201);
    }

    /**
     * Unregister FCM token
     */
    public function unregisterToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'device_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        DB::table('fcm_tokens')
            ->where('user_id', $request->user_id)
            ->where('device_id', $request->device_id)
            ->update(['is_active' => 0]);

        return response()->json([
            'status' => true,
            'message' => 'FCM token unregistered'
        ]);
    }

    /**
     * Send FCM push notification
     *
     * @param int $notificationId
     * @param string $token
     * @param array $data
     */
    private function sendFCM($notificationId, $token, $data)
    {
        $fcmServerKey = env('FCM_SERVER_KEY');

        if (!$fcmServerKey) {
            Log::warning('FCM_SERVER_KEY not configured');
            return;
        }

        $notification = [
            'title' => $data['title'],
            'body' => $data['body'],
        ];

        if (isset($data['image_url'])) {
            $notification['image'] = $data['image_url'];
        }

        $message = [
            'to' => $token,
            'notification' => $notification,
            'data' => $data['data'] ?? [],
            'priority' => $data['priority'] === 'high' ? 'high' : 'normal',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $fcmServerKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', $message);

            $result = $response->json();

            if ($response->successful() && isset($result['success']) && $result['success'] > 0) {
                // Update notification as sent
                DB::table('notifications')
                    ->where('id', $notificationId)
                    ->update([
                        'is_sent' => 1,
                        'sent_at' => now(),
                        'fcm_token' => $token,
                        'fcm_message_id' => $result['results'][0]['message_id'] ?? null,
                    ]);

                Log::info('FCM sent successfully', ['notification_id' => $notificationId]);
            } else {
                // Log error
                $error = $result['results'][0]['error'] ?? 'Unknown error';
                DB::table('notifications')
                    ->where('id', $notificationId)
                    ->update([
                        'error_message' => $error,
                        'retry_count' => DB::raw('retry_count + 1')
                    ]);

                Log::error('FCM send failed', [
                    'notification_id' => $notificationId,
                    'error' => $error
                ]);
            }
        } catch (\Exception $e) {
            DB::table('notifications')
                ->where('id', $notificationId)
                ->update([
                    'error_message' => $e->getMessage(),
                    'retry_count' => DB::raw('retry_count + 1')
                ]);

            Log::error('FCM send exception', [
                'notification_id' => $notificationId,
                'exception' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get notification statistics
     */
    public function stats(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        $stats = [
            'total' => DB::table('notifications')->where('user_id', $request->user_id)->count(),
            'unread' => DB::table('notifications')->where('user_id', $request->user_id)->where('is_read', 0)->count(),
            'sent' => DB::table('notifications')->where('user_id', $request->user_id)->where('is_sent', 1)->count(),
            'by_type' => DB::table('notifications')
                ->select('type', DB::raw('count(*) as count'))
                ->where('user_id', $request->user_id)
                ->groupBy('type')
                ->get()
        ];

        return response()->json([
            'status' => true,
            'data' => $stats
        ]);
    }
}
