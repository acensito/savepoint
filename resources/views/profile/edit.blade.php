@extends('layouts.app')

@section('content')
    @php
        $input = 'w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none';
        $label = 'block font-medium text-sm text-slate-300 mb-1';
        $error = 'text-red-400 text-sm mt-1 block';
    @endphp

    <div class="max-w-2xl mx-auto py-6">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Mi Perfil</h1>
            <p class="text-slate-400 mt-1">Gestiona los datos de tu cuenta y tu contraseña.</p>
        </div>

        <!-- Datos de la cuenta + Avatar (formulario unificado) -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-8 mb-6">

            <form id="profile-form" action="{{ route('web.profile.update') }}" method="POST"
                  enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                {{-- Flag oculto para borrado de avatar --}}
                <input type="hidden" name="remove_avatar" id="remove-avatar-flag" value="0">

                <!-- Sección Avatar -->
                <div>
                    <h2 class="text-lg font-semibold text-slate-100 mb-4">Avatar</h2>

                    <div class="flex items-center gap-6">
                        {{-- Avatar circular grande --}}
                        <div class="flex-shrink-0" style="width: 96px; height: 96px;">
                            @if($user->avatarUrl())
                                <img id="avatar-preview-img"
                                     src="{{ $user->avatarUrl() }}"
                                     alt="Avatar de {{ $user->name }}"
                                     class="w-24 h-24 rounded-full object-cover shadow-md"
                                     style="width: 96px; height: 96px; border-radius: 9999px; object-fit: cover;">
                                <div id="avatar-preview-default"
                                     class="hidden w-24 h-24 rounded-full bg-slate-800 items-center justify-center text-slate-400 shadow-md overflow-hidden"
                                     style="width: 96px; height: 96px; border-radius: 9999px;">
                                    <x-gicon name="person" :filled="true" class="text-slate-300" style="font-size: 64px;" />
                                </div>
                            @else
                                <img id="avatar-preview-img"
                                     src=""
                                     alt="Avatar de {{ $user->name }}"
                                     class="hidden w-24 h-24 rounded-full object-cover shadow-md"
                                     style="width: 96px; height: 96px; border-radius: 9999px; object-fit: cover;">
                                <div id="avatar-preview-default"
                                     class="flex w-24 h-24 rounded-full bg-slate-800 items-center justify-center text-slate-400 shadow-md overflow-hidden"
                                     style="width: 96px; height: 96px; border-radius: 9999px;">
                                    <x-gicon name="person" :filled="true" class="text-slate-300" style="font-size: 64px;" />
                                </div>
                            @endif
                        </div>

                        {{-- Acciones del Avatar --}}
                        <div class="flex-1 space-y-2">
                            <div class="relative flex flex-wrap items-center gap-3">
                                {{-- Input de archivo accesible por teclado como hermano directo del label --}}
                                <input type="file"
                                       name="avatar"
                                       id="avatar-input"
                                       accept="image/jpeg,image/png,image/gif,image/webp"
                                       class="sr-only peer">

                                <label for="avatar-input"
                                       tabindex="0"
                                       role="button"
                                       aria-label="Cambiar imagen de avatar"
                                       class="cursor-pointer inline-flex items-center gap-2 bg-[var(--color-navbar)] hover:bg-[var(--color-navbar-hover)] text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-300">
                                    <x-gicon name="upload" class="text-[18px]" />
                                    <span>Cambiar avatar</span>
                                </label>

                                @if($user->avatarUrl())
                                    <button type="button"
                                            id="remove-avatar-btn"
                                            class="inline-flex items-center gap-1.5 text-sm font-medium text-red-400 hover:text-red-300 px-3 py-2 rounded-lg hover:bg-red-500/10 transition-colors focus:outline-none focus:ring-2 focus:ring-red-400">
                                        <x-gicon name="delete" class="text-[18px]" />
                                        <span>Eliminar avatar</span>
                                    </button>
                                @endif
                            </div>

                            {{-- Estado del archivo en español --}}
                            <p id="avatar-file-name" class="text-xs text-slate-400">
                                Ningún archivo nuevo seleccionado
                            </p>

                            <p class="text-xs text-slate-500">
                                Formatos admitidos: JPEG, PNG, GIF o WebP · Máx. 2 MB
                            </p>
                        </div>
                    </div>

                    @error('avatar')
                        <span class="{{ $error }} mt-3">{{ $message }}</span>
                    @enderror
                </div>

                <div class="border-t border-slate-800"></div>

                <!-- Datos de la cuenta -->
                <div>
                    <h2 class="text-lg font-semibold text-slate-100 mb-5">Datos de la cuenta</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="name" class="{{ $label }}">Nombre</label>
                            <input type="text" name="name" id="name"
                                   value="{{ old('name', $user->name) }}"
                                   required autofocus class="{{ $input }}">
                            @error('name') <span class="{{ $error }}">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="email" class="{{ $label }}">Email</label>
                            <input type="email" name="email" id="email"
                                   value="{{ old('email', $user->email) }}"
                                   required class="{{ $input }}">
                            @error('email') <span class="{{ $error }}">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end pt-2">
                    <button type="submit"
                            class="bg-[var(--color-navbar)] text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-navbar-hover)] transition-colors">
                        Guardar cambios
                    </button>
                </div>
            </form>
        </div>

        <!-- Cambiar contraseña -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
            <h2 class="text-lg font-semibold text-slate-100 mb-6">Cambiar contraseña</h2>

            <form action="{{ route('web.profile.password') }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="current_password" class="{{ $label }}">Contraseña actual</label>
                    <input type="password" name="current_password" id="current_password"
                           required class="{{ $input }}">
                    @error('current_password') <span class="{{ $error }}">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="{{ $label }}">Nueva contraseña</label>
                        <input type="password" name="password" id="password"
                               required class="{{ $input }}">
                        @error('password') <span class="{{ $error }}">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="{{ $label }}">Confirmar contraseña</label>
                        <input type="password" name="password_confirmation"
                               id="password_confirmation" required class="{{ $input }}">
                    </div>
                </div>

                <div class="flex items-center justify-end pt-2">
                    <button type="submit"
                            class="bg-[var(--color-navbar)] text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-navbar-hover)] transition-colors">
                        Actualizar contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script nonce="{{ $cspNonce }}">
        (function () {
            const input         = document.getElementById('avatar-input');
            const previewImg    = document.getElementById('avatar-preview-img');
            const previewDef    = document.getElementById('avatar-preview-default');
            const removeBtn     = document.getElementById('remove-avatar-btn');
            const removeFlag    = document.getElementById('remove-avatar-flag');
            const fileNameLabel = document.getElementById('avatar-file-name');

            // Preview en vivo al seleccionar una imagen
            if (input) {
                input.addEventListener('change', function () {
                    const file = this.files[0];
                    if (!file) return;

                    const reader = new FileReader();
                    reader.onload = function (e) {
                        if (previewImg) {
                            previewImg.src = e.target.result;
                            previewImg.classList.remove('hidden');
                            previewImg.style.display = 'block';
                        }
                        if (previewDef) {
                            previewDef.classList.add('hidden');
                            previewDef.classList.remove('flex');
                            previewDef.style.display = 'none';
                        }
                    };
                    reader.readAsDataURL(file);

                    if (fileNameLabel) {
                        const sizeKb = Math.round(file.size / 1024);
                        fileNameLabel.textContent = `Archivo seleccionado: ${file.name} (${sizeKb} KB)`;
                        fileNameLabel.className = 'text-xs text-indigo-300 font-medium';
                    }

                    // Cancelar cualquier solicitud de eliminación previa
                    if (removeFlag) removeFlag.value = '0';
                });
            }

            // Eliminar avatar: marcar flag y mostrar placeholder por defecto
            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    if (removeFlag) removeFlag.value = '1';

                    if (previewImg) {
                        previewImg.classList.add('hidden');
                        previewImg.style.display = 'none';
                    }
                    if (previewDef) {
                        previewDef.classList.remove('hidden');
                        previewDef.classList.add('flex');
                        previewDef.style.display = 'flex';
                    }

                    if (fileNameLabel) {
                        fileNameLabel.textContent = 'Avatar marcado para eliminar al guardar cambios';
                        fileNameLabel.className = 'text-xs text-amber-400 font-medium';
                    }

                    this.style.display = 'none';
                    if (input) input.value = '';
                });
            }

            // Accesibilidad de teclado: abrir el selector con Enter o Espacio al hacer foco en el botón
            const avatarLabel = document.querySelector('label[for="avatar-input"]');
            if (avatarLabel && input) {
                avatarLabel.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        input.click();
                    }
                });
            }
        })();
    </script>
@endsection
