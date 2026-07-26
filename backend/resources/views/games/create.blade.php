@extends('layouts.app')

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="mb-8 flex items-center gap-4">
            <a href="{{ route('web.games.index') }}" class="text-slate-400 hover:text-slate-900 transition-colors">
                <x-heroicon-o-arrow-left class="w-6 h-6" />
            </a>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Añadir nuevo juego</h1>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl p-8 shadow-sm">
            <!-- Formulario apuntando a la ruta store por método POST -->
            <form action="{{ route('web.games.store') }}" method="POST" class="space-y-6">
                @csrf <!-- Token de seguridad obligatorio en Laravel -->

                <!-- Título -->
                <div>
                    <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Título del juego *</label>
                    <input type="text" name="title" id="title" required
                        class="w-full rounded-lg border-slate-300 border px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none transition-colors">
                </div>

                <div class="grid grid-cols-2 gap-6">
                    <!-- Plataforma -->
                    <div>
                        <label for="platform_id" class="block text-sm font-medium text-slate-700 mb-1">Plataforma</label>
                        <select name="platform_id" id="platform_id" 
                            class="w-full rounded-lg border-slate-300 border px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none bg-white">
                            <option value="">Selecciona una...</option>
                            @foreach($platforms as $platform)
                                <option value="{{ $platform->id }}">{{ $platform->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Estado -->
                    <div>
                        <label for="play_status" class="block text-sm font-medium text-slate-700 mb-1">Estado</label>
                        <select name="play_status" id="play_status" required
                            class="w-full rounded-lg border-slate-300 border px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none bg-white">
                            <option value="pending">Pendiente</option>
                            <option value="playing">Jugando</option>
                            <option value="finished">Terminado</option>
                        </select>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <a href="{{ route('web.games.index') }}" class="px-5 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 rounded-lg transition-colors border border-transparent">
                        Cancelar
                    </a>
                    <button type="submit" 
                        class="px-5 py-2.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors shadow-sm flex items-center gap-2">
                        <x-heroicon-o-check class="w-4 h-4" />
                        Guardar Juego
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection