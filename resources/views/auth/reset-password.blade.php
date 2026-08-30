@include('partials.auth-head', ['title' => 'Restablecer contraseña'])

    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
        <h1 class="text-lg font-bold text-slate-100 mb-1">Restablecer contraseña</h1>
        <p class="text-slate-400 text-sm mb-6">Elige una nueva contraseña para tu cuenta.</p>

        @error('email')
        <div class="mb-4 text-sm text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-2">
            {{ $message }}
        </div>
        @enderror

        <form action="{{ route('password.update') }}" method="POST" class="space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="block font-medium text-sm text-slate-300 mb-1">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email', $email) }}" required autofocus
                       class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-hidden">
            </div>

            <div>
                <label for="password" class="block font-medium text-sm text-slate-300 mb-1">Nueva contraseña</label>
                <input type="password" name="password" id="password" required minlength="8"
                       class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-hidden">
                <p class="text-xs text-slate-500 mt-1">
                    Mínimo 8 caracteres, con al menos una mayúscula, una minúscula, un número y un símbolo.
                </p>
                @error('password') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block font-medium text-sm text-slate-300 mb-1">Confirmar
                    contraseña</label>
                <input type="password" name="password_confirmation" id="password_confirmation" required minlength="8"
                       class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-hidden">
            </div>

            <button type="submit"
                    class="w-full bg-indigo-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                Restablecer contraseña
            </button>
        </form>
    </div>
</div>

</body>
</html>
