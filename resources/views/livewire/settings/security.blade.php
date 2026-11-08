<section class="w-full" x-data>
    @include('partials.settings-heading')

    <h2 class="sr-only">Pengaturan Keamanan</h2>

    <x-settings.layout :heading="__('Keamanan')" :subheading="__('Kelola kata sandi dan keamanan akun Anda.')">
        <div class="flex flex-col gap-4 my-6 w-full max-w-xl">

            {{-- Password card --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex items-start gap-4">
                    <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700">
                        <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base font-semibold text-zinc-900">Kata Sandi</h3>
                        <p class="text-sm text-zinc-500 mt-0.5">Gunakan kata sandi kuat minimal 8 karakter dengan kombinasi huruf dan angka.</p>
                        <div class="mt-3">
                            <button type="button" wire:click="openPasswordModal" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-brand-600 text-white hover:bg-brand-700 font-semibold text-sm rounded-xl transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 active:bg-brand-800">Ubah Kata Sandi</button>
                        </div>
                    </div>
                </div>
            </div>

            @if ($canManageTwoFactor)
                {{-- Two-factor authentication card --}}
                <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-4">
                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700">
                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-zinc-900">Autentikasi Dua Faktor</h3>
                                @if ($twoFactorEnabled)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                        <span class="relative flex size-1.5">
                                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-60"></span>
                                            <span class="relative inline-flex size-1.5 rounded-full bg-emerald-500"></span>
                                        </span>
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-500/10 border border-zinc-500/20 px-2.5 py-1 text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                                        <span class="size-1.5 rounded-full bg-zinc-400"></span>
                                        Nonaktif
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm text-zinc-500 mt-0.5">Lapisan keamanan tambahan. Setiap masuk, Anda diminta kode 6 digit dari aplikasi authenticator selain kata sandi.</p>
                            <div class="mt-3 rounded-xl bg-zinc-50 border border-zinc-200 px-4 py-3 text-xs text-zinc-600 leading-relaxed">
                                <p class="font-semibold text-zinc-700">Cara pakai</p>
                                <ol class="mt-1 list-decimal pl-4 space-y-0.5">
                                    <li>Klik Aktifkan, lalu pindai kode QR dengan aplikasi authenticator (Google Authenticator, Microsoft Authenticator, atau 1Password).</li>
                                    <li>Masukkan kode 6 digit dari aplikasi untuk verifikasi.</li>
                                    <li>Simpan kode pemulihan di tempat aman untuk jaga jaga jika HP hilang.</li>
                                </ol>
                            </div>
                            <div class="mt-3">
                                @if ($twoFactorEnabled)
                                    <button type="button" wire:click="confirmDisableTwoFactor" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-zinc-300 text-zinc-700 hover:bg-zinc-50 font-semibold text-sm rounded-xl transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20">Nonaktifkan 2FA</button>
                                @else
                                    <button type="button" wire:click="enable" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-brand-600 text-white hover:bg-brand-700 font-semibold text-sm rounded-xl transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 active:bg-brand-800">Aktifkan 2FA</button>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($twoFactorEnabled)
                        <div class="mt-4">
                            <livewire:settings.two-factor.recovery-codes />
                        </div>
                    @endif
                </div>
            @endif

            @if ($canManagePasskeys)
                {{-- Passkey card --}}
                <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-4">
                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700">
                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243C9.05 3.457 10.471 3 12 3c4.142 0 7.5 3.358 7.5 7.5 0 2.92-.556 5.709-1.568 8.269M5.743 6.364C4.957 7.55 4.5 8.971 4.5 10.5c0 1.468.421 2.837 1.15 3.993M5.339 18.052c1.809-1.997 2.911-4.646 2.911-7.552 0-2.071 1.679-3.75 3.75-3.75s3.75 1.679 3.75 3.75c0 .527-.021 1.049-.064 1.565M12 10.5c0 3.723-1.356 7.129-3.601 9.751m6.634-4.597c-.548 1.92-1.394 3.714-2.485 5.329" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-zinc-900">Passkey</h3>
                                @if (count($this->passkeys) > 0)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 border border-brand-500/20 px-2.5 py-1 text-xs font-semibold text-brand-700 dark:text-brand-300">
                                        <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                                        </svg>
                                        {{ count($this->passkeys) }} terdaftar
                                    </span>
                                @endif
                            </div>
                            <p class="text-sm text-zinc-500 mt-0.5">Masuk tanpa kata sandi memakai fingerprint, Face ID, atau PIN perangkat Anda.</p>
                            <div class="mt-3 rounded-xl bg-zinc-50 border border-zinc-200 px-4 py-3 text-xs text-zinc-600 leading-relaxed">
                                <p class="font-semibold text-zinc-700">Cara pakai</p>
                                <ol class="mt-1 list-decimal pl-4 space-y-0.5">
                                    <li>Klik Tambah Passkey, beri nama yang mudah dikenali, misalnya HP Pribadi atau Laptop Kantor.</li>
                                    <li>Ikuti permintaan browser, lalu pilih fingerprint atau kunci layar perangkat saat diminta.</li>
                                    <li>Di halaman masuk, klik Masuk dengan passkey dan verifikasi dengan sidik jari atau PIN.</li>
                                </ol>
                            </div>
                            <div class="mt-3">
                                <button type="button" wire:click="openPasskeyModal" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-brand-600 text-white hover:bg-brand-700 font-semibold text-sm rounded-xl transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 active:bg-brand-800">Tambah Passkey</button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        @forelse ($this->passkeys as $passkey)
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 mb-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-zinc-900 truncate">{{ $passkey['name'] }}</p>
                                    <p class="text-xs text-zinc-500">
                                        {{ $passkey['authenticator'] ?? 'Passkey' }}
                                        @if ($passkey['last_used_at'])
                                            &middot; Terakhir dipakai {{ $passkey['last_used_at'] }}
                                        @elseif ($passkey['created_at'])
                                            &middot; Dibuat {{ $passkey['created_at'] }}
                                        @endif
                                    </p>
                                </div>
                                <button type="button" wire:click="confirmDeletePasskey({{ $passkey['id'] }})"
                                    class="shrink-0 px-3 py-1.5 text-xs font-semibold text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                                    Hapus
                                </button>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500">Belum ada passkey. Tambahkan satu agar bisa masuk tanpa kata sandi.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </x-settings.layout>

    {{-- Change password modal --}}
    @if ($showPasswordModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Ubah Kata Sandi">
            <button type="button" wire:click="closePasswordModal" aria-label="Tutup" class="absolute inset-0 cursor-default bg-white/70 backdrop-blur-[3px]"></button>
            <div class="relative w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl">
                <button type="button" wire:click="closePasswordModal" aria-label="Tutup"
                    class="absolute top-4 right-4 flex size-8 items-center justify-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 focus:outline-none">
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
                <form method="POST" wire:submit="updatePassword" class="flex flex-col gap-5">
                    <div class="text-center">
                        <span class="inline-flex size-11 items-center justify-center rounded-2xl bg-brand-100 text-brand-700">
                            <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                            </svg>
                        </span>
                        <h3 class="mt-3 text-lg font-semibold text-zinc-900">Ubah Kata Sandi</h3>
                        <p class="mt-1 text-sm text-zinc-500">Masukkan kata sandi lama, lalu buat kata sandi baru yang kuat.</p>
                    </div>

                    <div class="flex flex-col gap-0.5" x-data="{ show: false }">
                        <label for="modal_current_password" class="text-sm font-medium text-zinc-700">Kata Sandi Saat Ini</label>
                        <div class="relative">
                            <input id="modal_current_password" wire:model="current_password" :type="show ? 'text' : 'password'" required
                                autocomplete="current-password" placeholder="Masukkan kata sandi saat ini"
                                class="w-full bg-white shadow-sm rounded-xl border border-zinc-300 pl-3.5 pr-10 py-2.5 text-base text-zinc-900 placeholder-zinc-400 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all @error('current_password') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-zinc-400 hover:text-zinc-600 focus:outline-none">
                                <svg x-show="!show" class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg x-show="show" x-cloak class="size-5" style="display: none;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        @error('current_password')
                            <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-0.5" x-data="{ show: false }">
                        <label for="modal_new_password" class="text-sm font-medium text-zinc-700">Kata Sandi Baru</label>
                        <div class="relative">
                            <input id="modal_new_password" wire:model="password" :type="show ? 'text' : 'password'" required autocomplete="new-password"
                                placeholder="Masukkan kata sandi baru"
                                class="w-full bg-white shadow-sm rounded-xl border border-zinc-300 pl-3.5 pr-10 py-2.5 text-base text-zinc-900 placeholder-zinc-400 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all @error('password') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-zinc-400 hover:text-zinc-600 focus:outline-none">
                                <svg x-show="!show" class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg x-show="show" x-cloak class="size-5" style="display: none;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-zinc-500 mt-0.5">Gunakan minimal 8 karakter dengan kombinasi huruf dan angka.</p>
                        @error('password')
                            <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-0.5" x-data="{ show: false }">
                        <label for="modal_password_confirmation" class="text-sm font-medium text-zinc-700">Konfirmasi Kata Sandi Baru</label>
                        <div class="relative">
                            <input id="modal_password_confirmation" wire:model="password_confirmation" :type="show ? 'text' : 'password'" required
                                autocomplete="new-password" placeholder="Ulangi kata sandi baru"
                                class="w-full bg-white shadow-sm rounded-xl border border-zinc-300 pl-3.5 pr-10 py-2.5 text-base text-zinc-900 placeholder-zinc-400 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 transition-all">
                            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-zinc-400 hover:text-zinc-600 focus:outline-none">
                                <svg x-show="!show" class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg x-show="show" x-cloak class="size-5" style="display: none;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="closePasswordModal" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-zinc-300 text-zinc-700 hover:bg-zinc-50 font-semibold text-sm rounded-xl transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20">Batal</button>
                        <button type="submit" data-test="update-password-button" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-brand-600 text-white hover:bg-brand-700 font-semibold text-sm rounded-xl transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 active:bg-brand-800">Simpan Kata Sandi</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canManageTwoFactor && $showModal)
        {{-- Enable and verify two-factor authentication modal --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="{{ $this->modalConfig['title'] }}">
            <button type="button" wire:click="closeModal" aria-label="Tutup" class="absolute inset-0 cursor-default bg-white/70 backdrop-blur-[3px]"></button>
            <div class="relative w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl">
                <button type="button" wire:click="closeModal" aria-label="Tutup"
                    class="absolute top-4 right-4 flex size-8 items-center justify-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 focus:outline-none">
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="flex flex-col gap-5">
                    <div class="text-center">
                        <span class="inline-flex size-11 items-center justify-center rounded-2xl bg-brand-100 text-brand-700">
                            <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                        </span>
                        <h3 class="mt-3 text-lg font-semibold text-zinc-900">{{ $this->modalConfig['title'] }}</h3>
                        <p class="mt-1 text-sm text-zinc-500">{{ $this->modalConfig['description'] }}</p>
                    </div>

                    @error('setupData')
                        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $message }}</div>
                    @enderror

                    @if ($qrCodeSvg)
                        <div class="mx-auto max-w-55 [&>svg]:size-full [&>svg]:h-auto">{!! $qrCodeSvg !!}</div>
                    @endif

                    @if ($manualSetupKey)
                        <div class="rounded-xl bg-zinc-100 border border-zinc-200 p-3 font-mono text-xs break-all select-text text-zinc-800">{{ $manualSetupKey }}</div>
                    @endif

                    @if ($showVerificationStep)
                        <div class="rounded-xl bg-zinc-50 border border-zinc-200 px-4 py-3 text-xs text-zinc-600 leading-relaxed">
                            Buka aplikasi authenticator, lalu ketik kode 6 digit yang tampil di layar HP Anda.
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="two_factor_code" class="text-sm font-medium text-zinc-700">Kode Verifikasi</label>
                            <input id="two_factor_code" wire:model="code" type="text" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="123456"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-center text-lg font-semibold tracking-widest text-zinc-900 placeholder-zinc-300 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 @error('code') border-red-400 focus:border-red-400 focus:ring-red-400/20 @enderror">
                            @error('code')
                                <p class="text-xs text-red-600 mt-0.5">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="resetVerification" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-zinc-300 text-zinc-700 hover:bg-zinc-50 font-semibold text-sm rounded-xl transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20">Kembali</button>
                            <button type="button" wire:click="confirmTwoFactor" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-brand-600 text-white hover:bg-brand-700 font-semibold text-sm rounded-xl transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 active:bg-brand-800">{{ $this->modalConfig['buttonText'] }}</button>
                        </div>
                    @else
                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="closeModal" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-zinc-300 text-zinc-700 hover:bg-zinc-50 font-semibold text-sm rounded-xl transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20">Tutup</button>
                            <button type="button" wire:click="showVerificationIfNecessary" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-brand-600 text-white hover:bg-brand-700 font-semibold text-sm rounded-xl transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 active:bg-brand-800">{{ $this->modalConfig['buttonText'] }}</button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
    @if ($canManageTwoFactor && $showDisableTwoFactorModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Nonaktifkan 2FA">
            <button type="button" wire:click="cancelDisableTwoFactor" aria-label="Tutup" class="absolute inset-0 cursor-default bg-white/70 backdrop-blur-[3px]"></button>
            <div class="relative w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl">
                <button type="button" wire:click="cancelDisableTwoFactor" aria-label="Tutup"
                    class="absolute top-4 right-4 flex size-8 items-center justify-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 focus:outline-none">
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="flex flex-col gap-5">
                    <div class="text-center">
                        <span class="inline-flex size-11 items-center justify-center rounded-2xl bg-red-100 text-red-600">
                            <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </span>
                        <h3 class="mt-3 text-lg font-semibold text-zinc-900">Nonaktifkan 2FA?</h3>
                        <p class="mt-1 text-sm text-zinc-500">Akun Anda hanya dilindungi kata sandi. Kode pemulihan ikut terhapus.</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="cancelDisableTwoFactor" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-zinc-300 text-zinc-700 hover:bg-zinc-50 font-semibold text-sm rounded-xl transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20">Batal</button>
                        <button type="button" wire:click="disable" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-red-600 text-white hover:bg-red-700 font-semibold text-sm rounded-xl transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-red-500/30 active:bg-red-800">Ya, Nonaktifkan</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
    @if ($canManagePasskeys && $showPasskeyModal)
        {{-- Add passkey modal --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Tambah Passkey" x-data="passkeyManager()" x-init="init()">
            <button type="button" wire:click="closePasskeyModal" aria-label="Tutup" class="absolute inset-0 cursor-default bg-white/70 backdrop-blur-[3px]"></button>
            <div class="relative w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl">
                <button type="button" wire:click="closePasskeyModal" aria-label="Tutup"
                    class="absolute top-4 right-4 flex size-8 items-center justify-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 focus:outline-none">
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="flex flex-col gap-5">
                    <div class="text-center">
                        <span class="inline-flex size-11 items-center justify-center rounded-2xl bg-brand-100 text-brand-700">
                            <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243C9.05 3.457 10.471 3 12 3c4.142 0 7.5 3.358 7.5 7.5 0 2.92-.556 5.709-1.568 8.269M5.743 6.364C4.957 7.55 4.5 8.971 4.5 10.5c0 1.468.421 2.837 1.15 3.993M5.339 18.052c1.809-1.997 2.911-4.646 2.911-7.552 0-2.071 1.679-3.75 3.75-3.75s3.75 1.679 3.75 3.75c0 .527-.021 1.049-.064 1.565M12 10.5c0 3.723-1.356 7.129-3.601 9.751m6.634-4.597c-.548 1.92-1.394 3.714-2.485 5.329" />
                            </svg>
                        </span>
                        <h3 class="mt-3 text-lg font-semibold text-zinc-900">Tambah Passkey</h3>
                        <p class="mt-1 text-sm text-zinc-500">Daftarkan sidik jari, Face ID, atau PIN perangkat ini sebagai kunci masuk.</p>
                    </div>

                    <div class="rounded-xl bg-zinc-50 border border-zinc-200 px-4 py-3 text-xs text-zinc-600 leading-relaxed">
                        <p class="font-semibold text-zinc-700">Langkahnya</p>
                        <ol class="mt-1 list-decimal pl-4 space-y-0.5">
                            <li>Beri nama yang mudah dikenali, misalnya HP Pribadi atau Laptop Kantor.</li>
                            <li>Klik Daftarkan, lalu ikuti permintaan browser di layar.</li>
                            <li>Pilih fingerprint atau kunci layar perangkat saat diminta.</li>
                        </ol>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="passkey_name" class="text-sm font-medium text-zinc-700">Nama Passkey</label>
                        <input id="passkey_name" x-model="name" type="text" maxlength="50" placeholder="Contoh: HP Pribadi"
                            class="w-full rounded-xl border border-zinc-300 bg-white px-3.5 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                    </div>

                    <p x-show="!supported" x-cloak class="text-xs text-red-600">Browser ini belum mendukung passkey. Coba Chrome, Edge, atau Safari versi terbaru.</p>
                    <p x-show="error" x-cloak x-text="error" class="text-xs text-red-600"></p>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="closePasskeyModal" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-zinc-300 text-zinc-700 hover:bg-zinc-50 font-semibold text-sm rounded-xl transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20">Batal</button>
                        <button type="button" @click="register()" :disabled="busy || !name.trim()"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 disabled:opacity-50 text-white font-semibold text-sm transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-2 active:bg-brand-800 disabled:cursor-not-allowed">
                            <span x-show="!busy">Daftarkan</span>
                            <span x-show="busy" x-cloak>Mendaftarkan...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
    @if ($canManagePasskeys && $showDeletePasskeyModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Hapus Passkey">
            <button type="button" wire:click="cancelDeletePasskey" aria-label="Tutup" class="absolute inset-0 cursor-default bg-white/70 backdrop-blur-[3px]"></button>
            <div class="relative w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl">
                <button type="button" wire:click="cancelDeletePasskey" aria-label="Tutup"
                    class="absolute top-4 right-4 flex size-8 items-center justify-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 focus:outline-none">
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="flex flex-col gap-5">
                    <div class="text-center">
                        <span class="inline-flex size-11 items-center justify-center rounded-2xl bg-red-100 text-red-600">
                            <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </span>
                        <h3 class="mt-3 text-lg font-semibold text-zinc-900">Hapus Passkey?</h3>
                        <p class="mt-1 text-sm text-zinc-500">Passkey yang dihapus tidak bisa dipakai lagi untuk masuk. Tindakan ini tidak bisa dibatalkan.</p>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="cancelDeletePasskey" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-zinc-300 text-zinc-700 hover:bg-zinc-50 font-semibold text-sm rounded-xl transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500/20">Batal</button>
                        <button type="button" wire:click="deletePasskey" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-red-600 text-white hover:bg-red-700 font-semibold text-sm rounded-xl transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-red-500/30 active:bg-red-800">Ya, Hapus</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

@if ($canManagePasskeys)
<script>
function passkeyManager() {
    return {
        name: '',
        busy: false,
        error: '',
        supported: true,
        init() {
            this.supported = window.Passkeys ? window.Passkeys.isSupported() : false;
            if (!window.Passkeys) {
                window.addEventListener('passkeys:ready', () => {
                    this.supported = window.Passkeys.isSupported();
                }, { once: true });
            }
        },
        async register() {
            if (this.busy) {
                return;
            }
            this.error = '';
            if (!this.name.trim()) {
                return;
            }
            if (!window.Passkeys || !window.Passkeys.isSupported()) {
                this.supported = false;
                return;
            }
            window.Passkeys.cancel();
            this.busy = true;
            try {
                await window.Passkeys.register({ name: this.name.trim() });
                if (window.Swal) {
                    await window.Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: 'Passkey berhasil didaftarkan.',
                        timer: 2500,
                        timerProgressBar: true,
                        showConfirmButton: false,
                    });
                }
                window.location.reload();
            } catch (e) {
                const message = passkeyErrorMessage(e, 'Gagal mendaftarkan passkey. Coba lagi.');
                this.error = message;
                if (window.Swal) {
                    window.Swal.fire({
                        icon: 'error',
                        title: 'Gagal mendaftarkan',
                        text: message,
                        confirmButtonText: 'Coba Lagi',
                        confirmButtonColor: '#f59e0b',
                    });
                }
            } finally {
                this.busy = false;
            }
        },
    };
}
function passkeyErrorMessage(error, fallback) {
    const name = error && error.name ? error.name : '';
    const raw = error && error.message ? String(error.message) : '';
    if (name === 'UserCancelledError' || name === 'NotAllowedError' || /already pending/i.test(raw)) {
        return 'Jendela verifikasi sebelumnya masih terbuka. Tutup dulu jendela itu, lalu klik Daftarkan lagi.';
    }
    if (name === 'NotSupportedError') {
        return 'Browser ini belum mendukung passkey. Coba Chrome, Edge, atau Safari versi terbaru.';
    }
    if (name === 'PasskeyExistsError' || name === 'InvalidStateError') {
        return 'Perangkat ini sudah terdaftar sebagai passkey.';
    }
    if (name === 'InvalidDomainError' || /invalid domain/i.test(raw)) {
        return 'Passkey tidak bisa dipakai di alamat ini. Untuk pengembangan lokal, buka lewat localhost.';
    }
    if (/expired/i.test(raw)) {
        return 'Sesi pendaftaran kedaluwarsa. Muat ulang halaman ini, lalu coba lagi.';
    }
    if (/not recognized|removed from your account/i.test(raw)) {
        return 'Passkey tidak dikenali. Mungkin sudah dihapus dari akun Anda.';
    }
    if (/Unable to sign in with this account/i.test(raw)) {
        return 'Tidak bisa masuk dengan akun ini.';
    }
    if (/Unable to (register|verify)/i.test(raw)) {
        return fallback;
    }
    if (/Invalid credential format/i.test(raw)) {
        return 'Format kredensial tidak valid.';
    }
    return fallback;
}
</script>
@endif
