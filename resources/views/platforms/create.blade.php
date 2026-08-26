@extends('layouts.app')

@section('content')
    <div class="max-w-xl mx-auto py-6">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Nueva Plataforma</h1>
                <p class="text-slate-400 mt-1">Define su nombre, fabricante y, si quieres, sus propios colores.</p>
            </div>
            <a href="{{ route('web.platforms.index') }}" class="text-sm font-medium text-slate-400 hover:text-slate-100">
                ← Volver
            </a>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
            <form action="{{ route('web.platforms.store') }}" method="POST" class="space-y-6">
                @csrf
                @include('platforms._form', ['platform' => null])

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                    <a href="{{ route('web.platforms.index') }}" class="text-slate-400 hover:text-slate-100 text-sm font-medium px-4 py-2">Cancelar</a>
                    <button type="submit" class="bg-(--color-navbar) text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-(--color-navbar-hover) transition-colors">
                        Guardar Plataforma
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
