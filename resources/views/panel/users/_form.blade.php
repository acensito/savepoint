@php
    $input = 'w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-hidden';
    $label = 'block font-medium text-sm text-slate-300 mb-1';
    $error = 'text-red-400 text-sm mt-1 block';

    $user ??= null;
    $isSelf = $user && $user->id === auth()->id();
@endphp

<div class="space-y-4">
    <div>
        <label for="name" class="{{ $label }}">Nombre</label>
        <input type="text" name="name" id="name" value="{{ old('name', $user?->name) }}" required autofocus class="{{ $input }}">
        @error('name') <span class="{{ $error }}">{{ $message }}</span> @enderror
    </div>

    <div>
        <label for="email" class="{{ $label }}">Email</label>
        <input type="email" name="email" id="email" value="{{ old('email', $user?->email) }}" required class="{{ $input }}">
        @error('email') <span class="{{ $error }}">{{ $message }}</span> @enderror
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="password" class="{{ $label }}">Contraseña</label>
            <input type="password" name="password" id="password" autocomplete="new-password"
                placeholder="{{ $user ? 'Dejar en blanco para no cambiarla' : '' }}" {{ $user ? '' : 'required' }}
                class="{{ $input }}">
            <p class="text-xs text-slate-500 mt-1">
                Mínimo 8 caracteres, con al menos una mayúscula, una minúscula, un número y un símbolo.
            </p>
            @error('password') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="{{ $label }}">Confirmar contraseña</label>
            <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" class="{{ $input }}">
        </div>
    </div>

    <div class="flex items-center gap-2">
        <input type="checkbox" name="is_admin" id="is_admin" value="1"
            {{ old('is_admin', $user?->is_admin) ? 'checked' : '' }} {{ $isSelf ? 'disabled' : '' }}
            class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50">
        <label for="is_admin" class="text-sm text-slate-300">Administrador</label>
        @error('is_admin') <span class="{{ $error }}">{{ $message }}</span> @enderror
    </div>
    @if($isSelf)
        <p class="text-xs text-slate-500">No puedes quitarte el rol de administrador a ti mismo.</p>
    @endif
</div>
