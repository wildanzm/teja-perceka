<?php

namespace App\Livewire\SuperAdmin;

use App\Models\BusinessUnit;
use App\Models\User;
use App\Support\RoleLabels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class UserManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $selectedRole = '';

    public ?int $editingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $editRole = '';

    public $editBusinessUnitId = null;

    public bool $editIsActive = true;

    public string $editPassword = '';

    public string $editPassword_confirmation = '';

    public bool $showCreateModal = false;

    public string $createName = '';

    public string $createEmail = '';

    public string $createPassword = '';

    public string $createPassword_confirmation = '';

    public string $createRole = '';

    public $createBusinessUnitId = null;

    public bool $createIsActive = true;

    public ?int $deleteId = null;

    public bool $showDeleteModal = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedRole(): void
    {
        $this->resetPage();
    }

    public function editUser(int $userId): void
    {
        $user = User::with('roles')->findOrFail($userId);
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->editRole = $user->roles->first()?->name ?? '';
        $this->editBusinessUnitId = $user->business_unit_id;
        $this->editIsActive = (bool) ($user->is_active ?? true);
        $this->editPassword = '';
        $this->editPassword_confirmation = '';
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingUserId', 'name', 'email', 'editRole', 'editBusinessUnitId', 'editIsActive', 'editPassword', 'editPassword_confirmation']);
    }

    /**
     * The last super_admin holder must keep the role and stay active,
     * otherwise nobody can open the super-admin routes anymore.
     */
    private function isLastSuperAdmin(User $user): bool
    {
        return $user->hasRole('super_admin') && User::role('super_admin')->count() <= 1;
    }

    public function saveUser(): void
    {
        try {
            $validated = $this->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email,'.$this->editingUserId,
                'editRole' => ['required', Rule::in(RoleLabels::ORDER)],
                'editBusinessUnitId' => 'nullable|exists:business_units,id',
                'editIsActive' => 'boolean',
                'editPassword' => 'nullable|string|min:8|confirmed',
            ], [], [
                'editRole' => 'hak akses',
                'editBusinessUnitId' => 'unit usaha',
                'editIsActive' => 'status',
                'editPassword' => 'password baru',
            ]);
        } catch (ValidationException $e) {
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal menyimpan', text: (string) $e->validator->errors()->first());

            throw $e;
        }

        if ($validated['editRole'] === 'kepala_unit' && empty($validated['editBusinessUnitId'])) {
            $this->addError('editBusinessUnitId', 'Unit usaha wajib dipilih untuk Kepala Unit.');
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal menyimpan', text: 'Unit usaha wajib dipilih untuk Kepala Unit.');

            return;
        }

        if ($this->editingUserId === Auth::id() && $validated['editRole'] !== 'super_admin') {
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal menyimpan', text: 'Role akun sendiri tidak dapat diubah.');

            return;
        }

        $user = User::findOrFail($this->editingUserId);

        if ($this->isLastSuperAdmin($user) && ($validated['editRole'] !== 'super_admin' || ! $validated['editIsActive'])) {
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal menyimpan', text: 'Tidak dapat menonaktifkan super admin terakhir.');

            return;
        }

        $wasActive = (bool) ($user->is_active ?? true);
        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'business_unit_id' => $validated['editRole'] === 'kepala_unit' ? $validated['editBusinessUnitId'] : null,
        ];

        if ($validated['email'] !== $user->email) {
            $data['email_verified_at'] = null;
        }

        if ($user->id !== Auth::id()) {
            $data['is_active'] = (bool) $validated['editIsActive'];
        }

        if (! empty($validated['editPassword'])) {
            $data['password'] = $validated['editPassword'];
        }

        $user->update($data);

        if ($wasActive && ! $user->is_active) {
            $user->increment('session_version');
        }

        $user->syncRoles([$validated['editRole']]);

        $this->cancelEdit();

        $this->dispatch('swal-alert', icon: 'success', title: 'Berhasil', text: 'Data pengguna berhasil diperbarui.');
    }

    public function openCreate(): void
    {
        $this->reset(['createName', 'createEmail', 'createPassword', 'createPassword_confirmation', 'createRole', 'createBusinessUnitId']);
        $this->createIsActive = true;
        $this->resetErrorBag();
        $this->showCreateModal = true;
    }

    public function closeCreate(): void
    {
        $this->reset(['showCreateModal', 'createName', 'createEmail', 'createPassword', 'createPassword_confirmation', 'createRole', 'createBusinessUnitId']);
        $this->createIsActive = true;
        $this->resetErrorBag();
    }

    public function storeUser(): void
    {
        try {
            $validated = $this->validate([
                'createName' => 'required|string|max:255',
                'createEmail' => 'required|email|max:255|unique:users,email',
                'createPassword' => 'required|string|min:8|confirmed',
                'createRole' => ['required', Rule::in(RoleLabels::ORDER)],
                'createBusinessUnitId' => 'nullable|exists:business_units,id',
                'createIsActive' => 'boolean',
            ], [], [
                'createName' => 'nama',
                'createEmail' => 'email',
                'createPassword' => 'password',
                'createRole' => 'hak akses',
                'createBusinessUnitId' => 'unit usaha',
                'createIsActive' => 'status',
            ]);
        } catch (ValidationException $e) {
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal menyimpan', text: (string) $e->validator->errors()->first());

            throw $e;
        }

        if ($validated['createRole'] === 'kepala_unit' && empty($validated['createBusinessUnitId'])) {
            $this->addError('createBusinessUnitId', 'Unit usaha wajib dipilih untuk Kepala Unit.');
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal menyimpan', text: 'Unit usaha wajib dipilih untuk Kepala Unit.');

            return;
        }

        $user = User::create([
            'name' => $validated['createName'],
            'email' => $validated['createEmail'],
            'password' => $validated['createPassword'],
            'business_unit_id' => $validated['createRole'] === 'kepala_unit' ? $validated['createBusinessUnitId'] : null,
            'is_active' => (bool) $validated['createIsActive'],
        ]);
        $user->assignRole($validated['createRole']);

        $this->closeCreate();
        $this->resetPage();

        $this->dispatch('swal-alert', icon: 'success', title: 'Berhasil', text: 'Pengguna baru berhasil ditambahkan.');
    }

    public function confirmDelete(int $userId): void
    {
        User::findOrFail($userId);
        $this->deleteId = $userId;
        $this->showDeleteModal = true;
    }

    public function executeDelete(): void
    {
        if (! $this->deleteId) {
            abort(403);
        }

        $user = User::findOrFail($this->deleteId);

        if ($user->id === Auth::id()) {
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal', text: 'Tidak dapat menghapus akun sendiri.');
            $this->reset(['showDeleteModal', 'deleteId']);

            return;
        }

        if ($this->isLastSuperAdmin($user)) {
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal', text: 'Tidak dapat menghapus super admin terakhir.');
            $this->reset(['showDeleteModal', 'deleteId']);

            return;
        }

        $user->delete();

        $this->reset(['showDeleteModal', 'deleteId']);
        $this->resetPage();

        $this->dispatch('swal-alert', icon: 'success', title: 'Berhasil', text: 'Pengguna berhasil dihapus.');
    }

    public function render()
    {
        [$roleOrder, $bindings] = RoleLabels::caseSql(User::class);

        $users = User::with(['roles', 'businessUnit'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->selectedRole !== '', function ($query) {
                $query->whereHas('roles', function ($q) {
                    $q->where('name', $this->selectedRole);
                });
            })
            ->orderByRaw($roleOrder, $bindings)
            ->orderBy('name', 'asc')
            ->paginate(12);

        return view('livewire.super-admin.user-manager', [
            'users' => $users,
            'roles' => RoleLabels::existingOrdered(),
            'units' => BusinessUnit::orderBy('name')->get(),
            'allRoles' => RoleLabels::ORDER,
        ])->layout('layouts.app', ['title' => 'Manajemen User Super Admin']);
    }
}
