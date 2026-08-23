@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Usuarios</h1>
            <p class="text-slate-400 mt-1">Cuentas con acceso a esta plataforma.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('web.panel.index') }}" class="text-sm font-medium text-slate-400 hover:text-slate-100">
                ← Volver al panel
            </a>
            <a href="{{ route('web.panel.users.create') }}" class="bg-indigo-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors whitespace-nowrap">
                Nuevo usuario
            </a>
        </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 mb-6">
        <h2 class="text-base font-semibold text-slate-100 mb-1">Registro público</h2>
        <p class="text-sm text-slate-500 mb-4">
            Con esto activado, cualquiera puede crear su propia cuenta desde <code>/register</code>. Desactivado, solo
            se pueden dar de alta cuentas nuevas desde aquí.
        </p>

        <form action="{{ route('web.panel.registration.update') }}" method="POST" class="flex items-center gap-4">
            @csrf
            @method('PATCH')

            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="checkbox" name="registration_enabled" value="1" {{ $appSetting->registration_enabled ? 'checked' : '' }}
                    class="rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                Permitir que cualquiera se registre libremente
            </label>

            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                Guardar
            </button>
        </form>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800">
            <thead class="bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Nombre</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Email</th>
                    <th scope="col" class="px-6 py-3.5 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Rol</th>
                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Juegos</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Alta</th>
                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @foreach($users as $user)
                    <tr class="hover:bg-slate-800/40 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-slate-100">
                            {{ $user->name }}
                            @if($user->id === auth()->id())
                                <span class="text-xs font-normal text-slate-500">(tú)</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">{{ $user->email }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($user->is_admin)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-indigo-500/10 text-indigo-300 border border-indigo-500/30">Admin</span>
                            @else
                                <span class="text-sm text-slate-500">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-slate-300 tabular-nums">{{ $user->games_count }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">{{ $user->created_at->format('d/m/Y') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('web.panel.users.edit', $user->id) }}" class="text-indigo-400 hover:text-indigo-300 transition-colors">
                                    Editar
                                </a>

                                @if($user->id === auth()->id())
                                    <span class="text-slate-600" title="No puedes borrar tu propia cuenta">Borrar</span>
                                @elseif($user->games_count > 0)
                                    <span class="text-slate-600" title="Tiene {{ $user->games_count }} juego(s): bórralos o reasígnalos antes de poder borrar la cuenta">Borrar</span>
                                @else
                                    <form action="{{ route('web.panel.users.destroy', $user->id) }}" method="POST" class="inline js-confirm-delete"
                                        data-confirm-title="Borrar usuario"
                                        data-confirm-message="La cuenta «{{ $user->name }}» se eliminará permanentemente. Esta acción no se puede deshacer."
                                        data-confirm-accept="Borrar usuario">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-300 transition-colors">
                                            Borrar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
