<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\ChatSticker;
use App\Models\Courier;
use App\Models\PaymentMethod;
use App\Models\SocialMedia;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class MasterDataController extends Controller
{
    /**
     * Display a listing of admin users.
     */
    public function admins(Request $request)
    {
        $query = User::with('roles')->whereHas('roles', function ($q) use ($request) {
            $q->where('name', '!=', 'Customer');
            if ($request->filled('role') && $request->role !== 'Semua') {
                $q->where('name', $request->role);
            }
        })->select([
            'id',
            'name',
            'email',
            'phone_number',
            'avatar',
            'is_active',
            'last_active_at',
            'created_at',
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone_number', 'ilike', "%{$search}%");
            });
        }

        $perPage = $request->get('perPage', 10);
        $admins = $query->latest()->paginate($perPage)->withQueryString();

        // Roles for the dropdown when adding an admin (excluding Customer)
        $roles = Role::where('name', '!=', 'Customer')->get();

        return Inertia::render('Admin/MasterData/Admins', [
            'users' => $admins,
            'roles' => $roles,
            'filters' => [
                'search' => $request->search,
                'role' => $request->role,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created admin.
     */
    public function storeAdmin(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'is_active' => true,
        ]);

        $user->assignRole($validated['role']);

        return back()->with('success', 'Admin berhasil ditambahkan.');
    }

    /**
     * Update the specified admin.
     */
    public function updateAdmin(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'nullable|string|min:8',
            'role' => 'required|string|exists:roles,name',
        ]);

        $user->update([
            'name' => $validated['name'],
        ]);

        if (! empty($validated['password'])) {
            $user->update(['password' => bcrypt($validated['password'])]);
        }

        // Sync role (we assume admin has only 1 primary role for this UI)
        $user->syncRoles([$validated['role']]);

        return back()->with('success', 'Data admin berhasil diperbarui.');
    }

    /**
     * Remove the specified admin.
     */
    public function destroyAdmin(User $user)
    {
        if ($user->hasRole('Super Admin')) {
            return back()->with('error', 'Super Admin tidak dapat dihapus.');
        }

        if (auth()->id() === $user->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $user->delete();

        return back()->with('success', 'Admin berhasil dihapus.');
    }

    /**
     * Toggle active status.
     */
    public function toggleActiveAdmin(User $user)
    {
        if ($user->hasRole('Super Admin')) {
            return back()->with('error', 'Status Super Admin tidak dapat diubah.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', 'Status admin berhasil diubah.');
    }

    /**
     * Display a listing of customers.
     */
    public function customers(Request $request)
    {
        // Customers are those who have the 'Customer' role
        $query = User::with('roles')
            ->withCount('transactions')
            ->whereHas('roles', function ($q) {
                $q->where('name', 'Customer');
            })
            ->select([
                'id',
                'name',
                'email',
                'phone_number',
                'avatar',
                'gender',
                'birth_date',
                'coins_balance',
                'is_active',
                'last_active_at',
                'created_at',
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone_number', 'ilike', "%{$search}%");
            });
        }

        $perPage = $request->get('perPage', 10);
        $customers = $query->latest()->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/MasterData/Customers', [
            'users' => $customers,
            'filters' => [
                'search' => $request->search,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function storeCustomer(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'is_active' => true,
        ]);

        $user->assignRole('Customer');

        return back()->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    /**
     * Update the specified customer.
     */
    public function updateCustomer(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'nullable|string|min:8',
        ]);

        $user->update([
            'name' => $validated['name'],
        ]);

        if (! empty($validated['password'])) {
            $user->update(['password' => bcrypt($validated['password'])]);
        }

        return back()->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    /**
     * Remove the specified customer.
     */
    public function destroyCustomer(User $user)
    {
        $user->delete();

        return back()->with('success', 'Pelanggan berhasil dihapus.');
    }

    /**
     * Toggle active status.
     */
    public function toggleActiveCustomer(User $user)
    {
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', 'Status pelanggan berhasil diubah.');
    }

    /**
     * Display a listing of the roles.
     */
    public function roles(Request $request)
    {
        $query = Role::withCount('users');

        if ($request->filled('search')) {
            $query->where('name', 'ilike', "%{$request->search}%");
        }

        $roles = $query->paginate(10)->withQueryString();

        return Inertia::render('Admin/MasterData/Roles', [
            'roles' => $roles,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Display a listing of payment methods.
     */
    public function paymentMethods(Request $request)
    {
        $query = PaymentMethod::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'ilike', "%{$search}%");
        }

        $perPage = $request->get('perPage', 10);
        $paymentMethods = $query->latest()->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/MasterData/PaymentMethods', [
            'paymentMethods' => $paymentMethods,
            'filters' => [
                'search' => $request->search,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created payment method.
     */
    public function storePaymentMethod(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:manual,gateway',
            'bank_name' => 'nullable|string|max:255|required_if:type,manual',
            'account_number' => 'nullable|string|max:255|required_if:type,manual',
            'account_name' => 'nullable|string|max:255|required_if:type,manual',
            'api_key' => 'nullable|string|max:255|required_if:type,gateway',
            'api_secret' => 'nullable|string|max:255',
            'url' => 'nullable|url|max:255',
            'webhook_token' => 'nullable|string|max:255',
            'admin_fee' => 'nullable|numeric|min:0',
        ]);

        $settings = null;
        if ($validated['type'] === 'gateway') {
            $settings = [
                'url' => $request->url,
                'webhook_token' => $request->webhook_token,
            ];
        }

        $data = $validated;
        $data['settings'] = $settings;

        PaymentMethod::create(array_merge($data, ['is_active' => true]));

        return back()->with('success', 'Metode Pembayaran berhasil ditambahkan.');
    }

    /**
     * Update the specified payment method.
     */
    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:manual,gateway',
            'bank_name' => 'nullable|string|max:255|required_if:type,manual',
            'account_number' => 'nullable|string|max:255|required_if:type,manual',
            'account_name' => 'nullable|string|max:255|required_if:type,manual',
            'api_key' => 'nullable|string|max:255|required_if:type,gateway',
            'api_secret' => 'nullable|string|max:255',
            'url' => 'nullable|url|max:255',
            'webhook_token' => 'nullable|string|max:255',
            'admin_fee' => 'nullable|numeric|min:0',
        ]);

        // If type is switched, nullify the irrelevant fields
        if ($validated['type'] === 'manual') {
            $validated['api_key'] = null;
            $validated['api_secret'] = null;
            $validated['settings'] = null;
        } else {
            $validated['bank_name'] = null;
            $validated['account_number'] = null;
            $validated['account_name'] = null;
            $validated['settings'] = [
                'url' => $request->url,
                'webhook_token' => $request->webhook_token,
            ];
        }

        $paymentMethod->update($validated);

        return back()->with('success', 'Metode Pembayaran berhasil diperbarui.');
    }

    /**
     * Remove the specified payment method.
     */
    public function destroyPaymentMethod(PaymentMethod $paymentMethod)
    {
        $paymentMethod->delete();

        return back()->with('success', 'Metode Pembayaran berhasil dihapus.');
    }

    /**
     * Toggle active status of payment method.
     */
    public function toggleActivePaymentMethod(PaymentMethod $paymentMethod)
    {
        $paymentMethod->update(['is_active' => ! $paymentMethod->is_active]);

        return back()->with('success', 'Status metode pembayaran berhasil diubah.');
    }

    /**
     * Display a listing of couriers.
     */
    public function couriers(Request $request)
    {
        $query = Courier::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        $perPage = $request->get('perPage', 10);
        $couriers = $query->orderBy('order')->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/MasterData/Couriers', [
            'couriers' => $couriers,
            'filters' => [
                'search' => $request->search,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created courier.
     */
    public function storeCourier(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:couriers,code',
            'name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        Courier::create([
            'code' => strtolower($validated['code']),
            'name' => $validated['name'],
            'is_active' => $request->input('is_active', true),
        ]);

        return back()->with('success', 'Kurir berhasil ditambahkan.');
    }

    /**
     * Update the specified courier.
     */
    public function updateCourier(Request $request, Courier $courier)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:couriers,code,'.$courier->id,
            'name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $courier->update([
            'code' => strtolower($validated['code']),
            'name' => $validated['name'],
            'is_active' => $request->input('is_active', $courier->is_active),
        ]);

        return back()->with('success', 'Kurir berhasil diperbarui.');
    }

    /**
     * Remove the specified courier.
     */
    public function destroyCourier(Courier $courier)
    {
        $courier->delete();

        return back()->with('success', 'Kurir berhasil dihapus.');
    }

    /**
     * Toggle active status of courier.
     */
    public function toggleActiveCourier(Courier $courier)
    {
        $courier->update(['is_active' => ! $courier->is_active]);

        return back()->with('success', 'Status kurir berhasil diubah.');
    }

    /**
     * Display a listing of brands.
     */
    public function brands(Request $request)
    {
        $query = Brand::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        $perPage = $request->get('perPage', 10);
        $brands = $query->orderBy('order')->orderBy('name')->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/MasterData/Brands', [
            'brands' => $brands,
            'filters' => [
                'search' => $request->search,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created brand.
     */
    public function storeBrand(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
            'is_active' => 'nullable|boolean',
        ]);

        Brand::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'is_active' => $request->input('is_active', true),
        ]);

        return back()->with('success', 'Brand berhasil ditambahkan.');
    }

    /**
     * Update the specified brand.
     */
    public function updateBrand(Request $request, Brand $brand)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name,'.$brand->id,
            'is_active' => 'nullable|boolean',
        ]);

        $brand->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'is_active' => $request->input('is_active', $brand->is_active),
        ]);

        return back()->with('success', 'Brand berhasil diperbarui.');
    }

    /**
     * Remove the specified brand.
     */
    public function destroyBrand(Brand $brand)
    {
        $brand->delete();

        return back()->with('success', 'Brand berhasil dihapus.');
    }

    /**
     * Toggle active status of brand.
     */
    public function toggleActiveBrand(Brand $brand)
    {
        $brand->update(['is_active' => ! $brand->is_active]);

        return back()->with('success', 'Status brand berhasil diubah.');
    }

    /**
     * Display the courier delivery history.
     */
    public function courierHistory(User $user): Response|RedirectResponse
    {
        if (! $user->hasRole('Kurir Toko')) {
            return back()->with('error', 'User tersebut bukan merupakan Kurir Toko.');
        }

        $transactions = Transaction::where('courier_user_id', $user->id)
            ->with(['paymentMethod', 'user', 'customerAddress'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/MasterData/CourierHistory', [
            'courier' => $user,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Display a listing of social media links.
     */
    public function socialMedia(Request $request): Response
    {
        $query = SocialMedia::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('label', 'ilike', "%{$search}%")
                    ->orWhere('platform', 'ilike', "%{$search}%")
                    ->orWhere('url', 'ilike', "%{$search}%");
            });
        }

        $perPage = $request->get('perPage', 25);
        $socialMediaLinks = $query->orderBy('order')->orderBy('id')->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/MasterData/SocialMedia', [
            'socialMediaLinks' => $socialMediaLinks,
            'filters' => [
                'search' => $request->search,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created social media link.
     */
    public function storeSocialMedia(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'platform' => 'required|string|max:50',
            'label' => 'required|string|max:255',
            'url' => 'required|string|max:500',
            'icon' => 'nullable|string|max:100',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $maxOrder = SocialMedia::max('order') ?? 0;

        SocialMedia::create([
            'platform' => $validated['platform'],
            'label' => $validated['label'],
            'url' => $validated['url'],
            'icon' => $validated['icon'] ?? 'ti-brand-instagram',
            'order' => $validated['order'] ?? $maxOrder + 1,
            'is_active' => $request->input('is_active', true),
        ]);

        return back()->with('success', 'Akun media sosial berhasil ditambahkan.');
    }

    /**
     * Update the specified social media link.
     */
    public function updateSocialMedia(Request $request, SocialMedia $socialMedia): RedirectResponse
    {
        $validated = $request->validate([
            'platform' => 'required|string|max:50',
            'label' => 'required|string|max:255',
            'url' => 'required|string|max:500',
            'icon' => 'nullable|string|max:100',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $socialMedia->update([
            'platform' => $validated['platform'],
            'label' => $validated['label'],
            'url' => $validated['url'],
            'icon' => $validated['icon'] ?? $socialMedia->icon,
            'order' => $validated['order'] ?? $socialMedia->order,
            'is_active' => $request->input('is_active', $socialMedia->is_active),
        ]);

        return back()->with('success', 'Akun media sosial berhasil diperbarui.');
    }

    /**
     * Remove the specified social media link.
     */
    public function destroySocialMedia(SocialMedia $socialMedia): RedirectResponse
    {
        $socialMedia->delete();

        return back()->with('success', 'Akun media sosial berhasil dihapus.');
    }

    /**
     * Toggle active status of a social media link.
     */
    public function toggleActiveSocialMedia(SocialMedia $socialMedia): RedirectResponse
    {
        $socialMedia->update(['is_active' => ! $socialMedia->is_active]);

        return back()->with('success', 'Status media sosial berhasil diubah.');
    }

    /**
     * Reorder social media links.
     *
     * @param  array<int, array{id: int, order: int}>  $items
     */
    public function reorderSocialMedia(Request $request): RedirectResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|uuid|exists:social_media,id',
            'items.*.order' => 'required|integer|min:0',
        ]);

        foreach ($request->items as $item) {
            SocialMedia::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return back()->with('success', 'Urutan media sosial berhasil disimpan.');
    }

    /**
     * Display a listing of chat stickers.
     */
    public function stickers(Request $request): Response
    {
        $query = ChatSticker::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('category', 'ilike', "%{$search}%");
            });
        }

        $perPage = $request->get('perPage', 20);
        $stickers = $query->orderBy('order')->orderBy('name')->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/MasterData/Stickers', [
            'stickers' => $stickers,
            'filters' => [
                'search' => $request->search,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created chat sticker.
     */
    public function storeSticker(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'image' => 'required|image|mimes:png,jpg,jpeg,gif,webp|max:2048',
            'is_active' => 'nullable|boolean',
        ]);

        $path = $request->file('image')->store('chat-stickers', 'public');
        $maxOrder = ChatSticker::max('order') ?? 0;

        ChatSticker::create([
            'name' => $request->name,
            'category' => $request->category,
            'image_path' => $path,
            'order' => $maxOrder + 1,
            'is_active' => filter_var($request->input('is_active', true), FILTER_VALIDATE_BOOLEAN),
        ]);

        return back()->with('success', 'Stiker berhasil ditambahkan.');
    }

    /**
     * Update the specified chat sticker.
     */
    public function updateSticker(Request $request, ChatSticker $chatSticker): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'image' => 'nullable|image|mimes:png,jpg,jpeg,gif,webp|max:2048',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($chatSticker->image_path);
            $chatSticker->image_path = $request->file('image')->store('chat-stickers', 'public');
        }

        $chatSticker->update([
            'name' => $request->name,
            'category' => $request->category,
            'image_path' => $chatSticker->image_path,
            'is_active' => filter_var($request->input('is_active', $chatSticker->is_active), FILTER_VALIDATE_BOOLEAN),
        ]);

        return back()->with('success', 'Stiker berhasil diperbarui.');
    }

    /**
     * Remove the specified chat sticker.
     */
    public function destroySticker(ChatSticker $chatSticker): RedirectResponse
    {
        Storage::disk('public')->delete($chatSticker->image_path);
        $chatSticker->delete();

        return back()->with('success', 'Stiker berhasil dihapus.');
    }

    /**
     * Toggle active status of a chat sticker.
     */
    public function toggleActiveSticker(ChatSticker $chatSticker): RedirectResponse
    {
        $chatSticker->update(['is_active' => ! $chatSticker->is_active]);

        return back()->with('success', 'Status stiker berhasil diubah.');
    }
}
