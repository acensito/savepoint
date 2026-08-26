@extends('layouts.app')

@section('content')
    <div class="max-w-2xl mx-auto py-6">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Nuevo usuario</h1>
                <p class="text-slate-400 mt-1">Da de alta una cuenta nueva en la plataforma.</p>
            </div>
            <a href="{{ route('web.panel.users.index') }}" class="text-sm font-medium text-slate-400 hover:text-slate-100">
                ← Volver a usuarios
            </a>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
            <form action="{{ route('web.panel.users.store') }}" method="POST" class="space-y-6">
                @csrf
                @include('panel.users._form', ['user' => null])

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                    <a href="{{ route('web.panel.users.index') }}" class="text-slate-400 hover:text-slate-100 text-sm font-medium px-4 py-2">Cancelar</a>
                    <button type="submit" class="bg-(--color-navbar) text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-(--color-navbar-hover) transition-colors">
                        Crear usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
