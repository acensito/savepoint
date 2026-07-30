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

        @if(session('success'))
            <div class="mb-6 text-sm text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 rounded-lg px-4 py-2.5">
                {{ session('success') }}
            </div>
        @endif

        <!-- Datos de la cuenta -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-8 mb-6">
            <h2 class="text-lg font-semibold text-slate-100 mb-6">Datos de la cuenta</h2>

            <form action="{{ route('web.profile.update') }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="{{ $label }}">Nombre</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required autofocus class="{{ $input }}">
                    @error('name') <span class="{{ $error }}">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="email" class="{{ $label }}">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required class="{{ $input }}">
                    @error('email') <span class="{{ $error }}">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center justify-end pt-2">
                    <button type="submit" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                        Guardar datos
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
                    <input type="password" name="current_password" id="current_password" required class="{{ $input }}">
                    @error('current_password') <span class="{{ $error }}">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="{{ $label }}">Nueva contraseña</label>
                        <input type="password" name="password" id="password" required class="{{ $input }}">
                        @error('password') <span class="{{ $error }}">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="{{ $label }}">Confirmar contraseña</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" required class="{{ $input }}">
                    </div>
                </div>

                <div class="flex items-center justify-end pt-2">
                    <button type="submit" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                        Actualizar contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
