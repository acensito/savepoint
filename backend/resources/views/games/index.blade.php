@extends('layouts.app')

@section('content')
    <div class="mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Mi Colección</h1>
            <p class="text-slate-500 mt-1">Tienes {{ $games->count() }} juegos registrados.</p>
        </div>
        
        <div class="flex gap-2">
            <button class="p-2 border border-slate-200 rounded-lg text-slate-500 hover:bg-slate-50 transition-colors" title="Filtrar">
                <x-heroicon-o-funnel class="w-5 h-5" />
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($games as $game)
            <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow group flex flex-col justify-between">
                
                <div>
                    <div class="flex justify-between items-start mb-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-indigo-50 text-indigo-700">
                            <x-heroicon-o-cpu-chip class="w-4 h-4" />
                            {{ $game->platform->name ?? 'Sin plataforma' }}
                        </span>
                        
                        <button class="text-slate-400 hover:text-slate-900 transition-colors">
                            <x-heroicon-o-ellipsis-vertical class="w-5 h-5" />
                        </button>
                    </div>

                    <h2 class="text-lg font-bold text-slate-900 leading-tight mb-2 group-hover:text-indigo-600 transition-colors">
                        {{ $game->title }}
                    </h2>
                    
                    @if($game->genres)
                        <div class="flex flex-wrap gap-1 mt-3">
                            @foreach($game->genres as $genre)
                                <span class="text-[11px] font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
                                    {{ $genre }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex justify-between items-center text-sm">
                    <div class="flex items-center gap-1.5 {{ $game->play_status === 'finished' ? 'text-emerald-600' : 'text-slate-500' }}">
                        @if($game->play_status === 'finished')
                            <x-heroicon-o-check-circle class="w-5 h-5" />
                        @else
                            <x-heroicon-o-clock class="w-5 h-5" />
                        @endif
                        <span class="font-medium capitalize">
                            {{ $game->play_status ?? 'Pendiente' }}
                        </span>
                    </div>
                    
                    @if($game->rating)
                        <div class="flex items-center gap-1 font-semibold text-slate-700">
                            {{ $game->rating }}
                            <x-heroicon-s-star class="w-4 h-4 text-amber-400" />
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endsection