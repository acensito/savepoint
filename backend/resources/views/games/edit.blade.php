@extends('layouts.app')

@section('content')
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Editar Juego: {{ $game->title }}</h1>
        <p class="text-slate-400 mt-1">Modifica los datos del juego en tu colección.</p>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 max-w-2xl">
        <form action="{{ route('web.games.update', $game->id) }}" method="POST">
            @csrf
            @method('PUT')

            <!-- Título -->
            <div class="mb-4">
                <label for="title" class="block font-medium text-sm text-slate-300 mb-1">Título</label>
                <input type="text" name="title" id="title" value="{{ old('title', $game->title) }}" required class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none transition-colors">
                @error('title') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Plataforma -->
            <div class="mb-4">
                <label for="platform_id" class="block font-medium text-sm text-slate-300 mb-1">Plataforma</label>
                <select name="platform_id" id="platform_id" class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none transition-colors">
                    <option value="">Selecciona una plataforma</option>
                    @foreach($platforms as $platform)
                        <option value="{{ $platform->id }}" {{ (old('platform_id', $game->platform_id) == $platform->id) ? 'selected' : '' }}>
                            {{ $platform->name }}
                        </option>
                    @endforeach
                </select>
                @error('platform_id') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Estado -->
            <div class="mb-4">
                <label for="play_status" class="block font-medium text-sm text-slate-300 mb-1">Estado</label>
                <select name="play_status" id="play_status" class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none transition-colors">
                    <option value="pending" {{ (old('play_status', $game->play_status) == 'pending') ? 'selected' : '' }}>Pendiente</option>
                    <option value="playing" {{ (old('play_status', $game->play_status) == 'playing') ? 'selected' : '' }}>Jugando</option>
                    <option value="finished" {{ (old('play_status', $game->play_status) == 'finished') ? 'selected' : '' }}>Terminado</option>
                </select>
                @error('play_status') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Valoración -->
            <div class="mb-6">
                <label for="rating" class="block font-medium text-sm text-slate-300 mb-1">Valoración (1-5)</label>
                <input type="number" name="rating" id="rating" min="1" max="5" value="{{ old('rating', $game->rating) }}" class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none transition-colors">
                @error('rating') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Botones -->
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('web.games.index') }}" class="text-slate-400 hover:text-slate-100 text-sm font-medium px-4 py-2">Cancelar</a>
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">Actualizar Juego</button>
            </div>
        </form>
    </div>
@endsection
