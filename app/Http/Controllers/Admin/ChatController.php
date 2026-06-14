<?php

namespace App\Http\Controllers\Admin;

use App\Events\ChatMessageRead;
use App\Events\ChatMessageSent;
use App\Events\NotificationUpdated;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    /**
     * List all chat threads with pagination (Inertia page).
     */
    public function index(Request $request): Response
    {
        $query = Chat::with(['user', 'lastMessage'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('sender_type', 'user')->where('is_read', false)])
            ->orderByDesc('last_message_at');

        if ($search = $request->query('search')) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%"));
        }

        $chats = $query->paginate(30)->through(fn (Chat $chat) => [
            'id' => $chat->id,
            'subject' => $chat->subject,
            'status' => $chat->status,
            'last_message_at' => $chat->last_message_at?->toISOString(),
            'unread_count' => $chat->unread_count,
            'user' => [
                'id' => $chat->user->id,
                'name' => $chat->user->name,
                'email' => $chat->user->email,
                'avatar' => $chat->user->avatar,
            ],
            'last_message' => $chat->lastMessage ? [
                'body' => $chat->lastMessage->body,
                'sender_type' => $chat->lastMessage->sender_type,
                'attachment_type' => $chat->lastMessage->attachment_type,
            ] : null,
        ]);

        $totalUnread = Chat::whereHas('messages', fn ($q) => $q->where('sender_type', 'user')->where('is_read', false))->count();

        return Inertia::render('Admin/Chat/Index', compact('chats', 'totalUnread'));
    }

    /**
     * Show a single chat thread with all messages (Inertia page).
     */
    public function show(Request $request, Chat $chat): Response
    {
        $chat->load('user');

        $product = null;
        if ($chat->product_id) {
            $product = Product::with(['productPrice', 'images'])->find($chat->product_id);
        }

        $messages = $chat->messages()
            ->get()
            ->map(fn (ChatMessage $msg) => $this->formatMessage($msg));

        // Mark user messages as read
        $chat->messages()
            ->where('sender_type', 'user')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $admins = User::whereHas('roles', fn ($q) => $q->where('name', '!=', 'Customer'))
            ->orWhereDoesntHave('roles')
            ->get();

        foreach ($admins as $admin) {
            broadcast(new NotificationUpdated($admin->id));
        }

        $readIds = $chat->messages()->where('is_read', true)->pluck('id')->toArray();
        broadcast(new ChatMessageRead($readIds, $chat->id))->toOthers();

        $chatData = [
            'id' => $chat->id,
            'subject' => $chat->subject,
            'status' => $chat->status,
            'product_id' => $chat->product_id,
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->productPrice?->price ?? 0,
                'image' => $product->image ?: ($product->images->first()?->url ?? $product->images->first()?->path),
            ] : null,
            'user' => [
                'id' => $chat->user->id,
                'name' => $chat->user->name,
                'email' => $chat->user->email,
                'avatar' => $chat->user->avatar,
            ],
        ];

        // Also fetch chats list for the left panel split view
        $chatsQuery = Chat::with(['user', 'lastMessage'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('sender_type', 'user')->where('is_read', false)])
            ->orderByDesc('last_message_at');

        if ($search = $request->query('search')) {
            $chatsQuery->whereHas('user', fn ($q) => $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%"));
        }

        $chats = $chatsQuery->paginate(30)->through(fn (Chat $c) => [
            'id' => $c->id,
            'subject' => $c->subject,
            'status' => $c->status,
            'last_message_at' => $c->last_message_at?->toISOString(),
            'unread_count' => $c->unread_count,
            'user' => [
                'id' => $c->user->id,
                'name' => $c->user->name,
                'email' => $c->user->email,
                'avatar' => $c->user->avatar,
            ],
            'last_message' => $c->lastMessage ? [
                'body' => $c->lastMessage->body,
                'sender_type' => $c->lastMessage->sender_type,
                'attachment_type' => $c->lastMessage->attachment_type,
            ] : null,
        ]);

        $totalUnread = Chat::whereHas('messages', fn ($q) => $q->where('sender_type', 'user')->where('is_read', false))->count();

        return Inertia::render('Admin/Chat/Show', [
            'chat' => $chatData,
            'initialMessages' => $messages,
            'chats' => $chats,
            'totalUnread' => $totalUnread,
        ]);
    }

    /**
     * Poll new messages for a chat (JSON, used by Svelte polling).
     */
    public function pollMessages(Request $request, Chat $chat): JsonResponse
    {
        $afterId = $request->query('after_id');
        $afterMessage = $afterId ? ChatMessage::find($afterId) : null;

        $query = $chat->messages()->orderBy('created_at', 'asc');

        if ($afterMessage) {
            $query->where('created_at', '>=', $afterMessage->created_at);
        }

        $messagesCollection = $query->get();

        if ($afterMessage) {
            $index = $messagesCollection->contains('id', $afterMessage->id)
                ? $messagesCollection->search(fn ($msg) => $msg->id === $afterMessage->id)
                : false;

            if ($index !== false) {
                $messagesCollection = $messagesCollection->slice($index + 1)->values();
            }
        }

        $messages = $messagesCollection->map(fn (ChatMessage $msg) => $this->formatMessage($msg));

        // Mark user messages as read
        $chat->messages()
            ->where('sender_type', 'user')
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
     * Admin sends a reply message.
     */
    public function reply(Request $request, Chat $chat): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'nullable|string|max:5000',
            'image' => 'nullable|file|image|max:5120',
        ]);

        if (empty($validated['body']) && ! $request->hasFile('image')) {
            return response()->json(['error' => 'Pesan tidak boleh kosong'], 422);
        }

        $attachmentType = null;
        $attachmentData = null;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('chat-images', 'public');
            $attachmentType = 'image';
            $attachmentData = ['url' => Storage::url($path), 'path' => $path];
        }

        $msg = ChatMessage::create([
            'chat_id' => $chat->id,
            'sender_type' => 'admin',
            'sender_id' => auth()->id(),
            'body' => $validated['body'] ?? null,
            'attachment_type' => $attachmentType,
            'attachment_data' => $attachmentData,
            'is_read' => false,
        ]);

        $chat->update(['last_message_at' => now()]);

        $formatted = $this->formatMessage($msg);
        broadcast(new ChatMessageSent($formatted, $chat->id))->toOthers();

        broadcast(new NotificationUpdated($chat->user_id));

        return response()->json($formatted, 201);

    }

    /**
     * Delete the entire chat thread.
     */
    public function destroy(Request $request, Chat $chat): JsonResponse|RedirectResponse
    {
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

        return redirect()->route('admin.chats.index')->with('success', 'Percakapan berhasil dihapus');
    }

    /**
     * Delete a single message from the chat thread.
     */
    public function destroyMessage(Request $request, Chat $chat, ChatMessage $message): JsonResponse
    {
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
