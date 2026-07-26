@extends('layouts.app')

@section('content')
    <div class="max-w-3xl mx-auto py-6">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Añadir Juego</h1>
                <p class="text-slate-500 mt-1">Busca primero para comprobar si ya lo tienes en tu colección.</p>
            </div>
            <a href="{{ route('web.games.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                ← Volver
            </a>
        </div>

        <!-- Formulario de Búsqueda GET Tradicional -->
        <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm mb-8">
            <form action="{{ route('web.games.search') }}" method="GET" class="flex gap-3">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Título del juego o EAN..." required
                    class="w-full rounded-lg border border-slate-300 px-4 py-2.5 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 outline-none text-sm">
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors whitespace-nowrap shadow-sm">
                    Buscar
                </button>
            </form>
        </div>

        @if(request('q'))
            <div class="space-y-6">
                <!-- Coincidentes Locales -->
                <div>
                    <h2 class="text-lg font-bold text-slate-800 mb-3">En tu Colección Local</h2>
                    @forelse($localGames as $game)
                        <div class="bg-white border border-slate-200 rounded-xl p-4 flex justify-between items-center mb-3 shadow-sm">
                            <div>
                                <h3 class="font-bold text-slate-900">{{ $game->title }}</h3>
                                <span class="text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded font-medium mt-1 inline-block">
                                    {{ $game->platform?->name ?? 'Sin plataforma' }}
                                </span>
                            </div>
                            <a href="{{ route('web.games.edit', $game->id) }}" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-200 transition-colors">
                                Editar mi juego
                            </a>
                        </div>
                    @empty
                        <div class="bg-white border border-slate-100 rounded-xl p-4 text-slate-400 italic">
                            No hay coincidencias en tu colección para "{{ request('q') }}".
                        </div>
                    @endforelse
                </div>

                <!-- Vía de Escape Manual -->
                <div class="pt-6 border-t border-slate-200 text-center">
                    <p class="text-sm text-slate-600 mb-3">¿No aparece lo que buscas?</p>
                    <a href="{{ route('web.games.create-manual', ['title' => request('q')]) }}" class="inline-block bg-slate-900 text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors shadow-sm">
                        + Introducir datos manualmente
                    </a>
                </div>
            </div>
        @endif
    </div>
@endsection