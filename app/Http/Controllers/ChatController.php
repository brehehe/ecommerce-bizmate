<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ChatController extends Controller
{
    /**
     * List all chats for the authenticated user (Inertia page).
     */
    public function index(Request $request)
    {
        $chats = Chat::where('user_id', auth()->id())
            ->with('lastMessage')
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('sender_type', 'admin')->where('is_read', false)])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (Chat $chat) => [
                'id' => $chat->id,
                'subject' => $chat->subject,
                'product_id' => $chat->product_id,
                'status' => $chat->status,
                'last_message_at' => $chat->last_message_at?->toISOString(),
                'unread_count' => $chat->unread_count,
                'last_message' => $chat->lastMessage ? [
                    'body' => $chat->lastMessage->body,
                    'sender_type' => $chat->lastMessage->sender_type,
                    'attachment_type' => $chat->lastMessage->attachment_type,
                ] : null,
            ]);

        if ($request->expectsJson()) {
            return response()->json($chats);
        }

        return Inertia::render('Storefront/Chat', compact('chats'));
    }

    /**
     * Create a new chat thread.
     */
    public function createChat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'nullable|string|max:255',
            'product_id' => 'nullable|integer',
        ]);

        $productId = $validated['product_id'] ?? null;
        $chat = null;

        if ($productId) {
            $chat = Chat::where('user_id', auth()->id())
                ->where('product_id', $productId)
                ->where('status', 'open')
                ->first();
        }

        if (! $chat) {
            $chat = Chat::create([
                'user_id' => auth()->id(),
                'subject' => $validated['subject'] ?? null,
                'product_id' => $productId,
                'status' => 'open',
            ]);
        }

        return response()->json([
            'id' => $chat->id,
            'subject' => $chat->subject,
            'product_id' => $chat->product_id,
            'status' => $chat->status,
            'last_message_at' => $chat->last_message_at?->toISOString(),
            'unread_count' => 0,
            'last_message' => null,
            'messages' => [],
        ]);
    }

    /**
     * Poll messages for a specific chat (JSON, used by Svelte polling).
     */
    public function messages(Request $request, Chat $chat): JsonResponse
    {
        // Ensure ownership
        abort_if($chat->user_id !== auth()->id(), 403);

        $afterId = (int) $request->query('after_id', 0);

        $messages = $chat->messages()
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->get()
            ->map(fn (ChatMessage $msg) => $this->formatMessage($msg));

        // Mark admin messages as read
        $chat->messages()
            ->where('sender_type', 'admin')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        // Get IDs of all messages currently read in this chat
        $readIds = $chat->messages()
            ->where('is_read', true)
            ->pluck('id');

        return response()->json([
            'messages' => $messages,
            'read_ids' => $readIds,
        ]);
    }

    /**
     * Send a message in a chat thread.
     */
    public function store(Request $request, Chat $chat): JsonResponse
    {
        abort_if($chat->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'body' => 'nullable|string|max:5000',
            'attachment_type' => 'nullable|in:product,image',
            'attachment_data' => 'nullable',
            'image' => 'nullable|file|image|max:5120',
        ]);

        // If no body and no attachment, reject
        if (empty($validated['body']) && empty($validated['attachment_type']) && ! $request->hasFile('image')) {
            return response()->json(['error' => 'Pesan tidak boleh kosong'], 422);
        }

        $attachmentType = $validated['attachment_type'] ?? null;
        $attachmentData = $validated['attachment_data'] ?? null;

        if (is_string($attachmentData)) {
            $attachmentData = json_decode($attachmentData, true);
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('chat-images', 'public');
            $attachmentType = 'image';
            $attachmentData = ['url' => Storage::url($path), 'path' => $path];
        }

        $msg = ChatMessage::create([
            'chat_id' => $chat->id,
            'sender_type' => 'user',
            'sender_id' => auth()->id(),
            'body' => $validated['body'] ?? null,
            'attachment_type' => $attachmentType,
            'attachment_data' => $attachmentData,
            'is_read' => false,
        ]);

        $chat->update(['last_message_at' => now()]);

        return response()->json($this->formatMessage($msg), 201);
    }

    /**
     * Delete the entire chat thread.
     */
    public function destroy(Request $request, Chat $chat): JsonResponse|RedirectResponse
    {
        abort_if($chat->user_id !== auth()->id(), 403);

        // Delete any image attachments from storage
        $chat->messages()->where('attachment_type', 'image')->get()->each(function (ChatMessage $message): void {
            if (is_array($message->attachment_data) && isset($message->attachment_data['path'])) {
                Storage::disk('public')->delete((string) $message->attachment_data['path']);
            }
        });

        $chat->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('chats.index')->with('success', 'Percakapan berhasil dihapus');
    }

    /**
     * Delete a single message from the chat thread.
     */
    public function destroyMessage(Request $request, Chat $chat, ChatMessage $message): JsonResponse
    {
        abort_if($chat->user_id !== auth()->id(), 403);
        abort_if($message->chat_id !== $chat->id, 404);

        // Delete image attachment from storage if exists
        if ($message->attachment_type === 'image' && is_array($message->attachment_data) && isset($message->attachment_data['path'])) {
            Storage::disk('public')->delete((string) $message->attachment_data['path']);
        }

        $message->delete();

        return response()->json(['success' => true]);
    }

    /** @return array<string, mixed> */
    private function formatMessage(ChatMessage $msg): array
    {
        return [
            'id' => $msg->id,
            'chat_id' => $msg->chat_id,
            'sender_type' => $msg->sender_type,
            'sender_id' => $msg->sender_id,
            'body' => $msg->body,
            'attachment_type' => $msg->attachment_type,
            'attachment_data' => $msg->attachment_data,
            'is_read' => $msg->is_read,
            'created_at' => $msg->created_at->toISOString(),
            'time' => $msg->created_at->setTimezone('Asia/Jakarta')->format('H:i'),
        ];
    }
}
