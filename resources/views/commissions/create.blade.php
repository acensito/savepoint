@extends('layouts.app')

@section('content')
    @php
        $input = 'w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none';
        $label = 'block font-medium text-sm text-slate-300 mb-1';
        $error = 'text-red-400 text-sm mt-1 block';
    @endphp

    <div class="max-w-2xl mx-auto py-6">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Añadir encargo</h1>
                <p class="text-slate-400 mt-1">Un juego que te compran/envían, o que compras/envías tú a alguien.</p>
            </div>
            <a href="{{ route('web.commissions.index') }}" class="text-sm font-medium text-slate-400 hover:text-slate-100 whitespace-nowrap">
                ← Volver a encargos
            </a>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
            <form action="{{ route('web.commissions.store') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <span class="{{ $label }}">Dirección</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-1">
                        <label class="flex items-center gap-2 text-sm text-slate-300 border border-slate-700 rounded-lg px-4 py-2.5 cursor-pointer hover:bg-slate-800">
                            <input type="radio" name="direction" value="owed_to_me" {{ old('direction', 'owed_to_me') === 'owed_to_me' ? 'checked' : '' }}
                                class="text-indigo-600 focus:ring-indigo-500">
                            Me lo deben (lo recibiré)
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-300 border border-slate-700 rounded-lg px-4 py-2.5 cursor-pointer hover:bg-slate-800">
                            <input type="radio" name="direction" value="owed_by_me" {{ old('direction') === 'owed_by_me' ? 'checked' : '' }}
                                class="text-indigo-600 focus:ring-indigo-500">
                            Se lo debo (voy a enviarlo)
                        </label>
                    </div>
                    @error('direction') <span class="{{ $error }}">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="title" class="{{ $label }}">Título</label>
                    <input type="text" name="title" id="title" value="{{ old('title') }}" required autofocus class="{{ $input }}">
                    @error('title') <span class="{{ $error }}">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="platform_id" class="{{ $label }}">Plataforma</label>
                        <select name="platform_id" id="platform_id" class="{{ $input }}">
                            <option value="">Sin especificar</option>
                            @foreach($platforms as $platform)
                                <option value="{{ $platform->id }}" {{ old('platform_id') == $platform->id ? 'selected' : '' }}>
                                    {{ $platform->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('platform_id') <span class="{{ $error }}">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="counterparty_name" class="{{ $label }}">A quién</label>
                        <input type="text" name="counterparty_name" id="counterparty_name" value="{{ old('counterparty_name') }}" required
                            placeholder="Nombre de la persona" class="{{ $input }}">
                        @error('counterparty_name') <span class="{{ $error }}">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="price" class="{{ $label }}">Precio</label>
                        <input type="number" name="price" id="price" value="{{ old('price') }}" step="0.01" min="0" class="{{ $input }}">
                        @error('price') <span class="{{ $error }}">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="purchased_at" class="{{ $label }}">Fecha de compra</label>
                        <input type="date" name="purchased_at" id="purchased_at" value="{{ old('purchased_at') }}" class="{{ $input }}">
                        @error('purchased_at') <span class="{{ $error }}">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label for="notes" class="{{ $label }}">Notas</label>
                    <textarea name="notes" id="notes" rows="3" class="{{ $input }}">{{ old('notes') }}</textarea>
                    @error('notes') <span class="{{ $error }}">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                    <a href="{{ route('web.commissions.index') }}" class="text-slate-400 hover:text-slate-100 text-sm font-medium px-4 py-2">Cancelar</a>
                    <button type="submit" class="bg-[var(--color-navbar)] text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-navbar-hover)] transition-colors">
                        Añadir encargo
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
