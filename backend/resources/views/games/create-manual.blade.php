@extends('layouts.app')

@section('content')
    <div class="max-w-2xl mx-auto py-6">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Nuevo Juego Manual</h1>
                <p class="text-slate-500 mt-1">Rellena los datos para registrar el juego.</p>
            </div>
            <a href="{{ route('web.games.search') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                ← Volver a buscar
            </a>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-8 shadow-sm">
            <form action="{{ route('web.games.store') }}" method="POST" class="space-y-6">
                @csrf

                <!-- Título -->
                <div>
                    <label for="title" class="block font-medium text-sm text-slate-700 mb-1">Título</label>
                    <input type="text" name="title" id="title" value="{{ old('title', $prefilledTitle ?? '') }}" required 
                        class="w-full rounded-lg border border-slate-300 px-4 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 outline-none">
                    @error('title') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Plataforma -->
                <div>
                    <label for="platform_id" class="block font-medium text-sm text-slate-700 mb-1">Plataforma</label>
                    <select name="platform_id" id="platform_id" 
                        class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 outline-none">
                        <option value="">Selecciona una plataforma</option>
                        @foreach($platforms as $platform)
                            <option value="{{ $platform->id }}" {{ old('platform_id') == $platform->id ? 'selected' : '' }}>
                                {{ $platform->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('platform_id') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Estado -->
                <div>
                    <label for="play_status" class="block font-medium text-sm text-slate-700 mb-1">Estado</label>
                    <select name="play_status" id="play_status" 
                        class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 outline-none">
                        <option value="pending" {{ old('play_status') == 'pending' ? 'selected' : '' }}>Pendiente</option>
                        <option value="playing" {{ old('play_status') == 'playing' ? 'selected' : '' }}>Jugando</option>
                        <option value="finished" {{ old('play_status') == 'finished' ? 'selected' : '' }}>Terminado</option>
                    </select>
                    @error('play_status') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Valoración -->
                <div>
                    <label for="rating" class="block font-medium text-sm text-slate-700 mb-1">Valoración (1-5)</label>
                    <input type="number" name="rating" id="rating" min="1" max="5" value="{{ old('rating') }}" 
                        class="w-full rounded-lg border border-slate-300 px-4 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 outline-none">
                    @error('rating') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                </div>

                <!-- Botones -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <a href="{{ route('web.games.search') }}" class="text-slate-600 hover:text-slate-900 text-sm font-medium px-4 py-2">Cancelar</a>
                    <button type="submit" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors shadow-sm">
                        Guardar Juego
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection