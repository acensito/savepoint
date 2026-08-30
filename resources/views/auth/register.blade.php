@include('partials.auth-head', ['title' => 'Crear cuenta'])

    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
        <h1 class="text-lg font-bold text-slate-100 mb-1">Crear cuenta</h1>
        <p class="text-slate-400 text-sm mb-6">Regístrate para empezar a organizar tu colección.</p>

        @if (session('error'))
            <div class="mb-4 text-sm text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-2">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div
                class="mb-4 text-sm text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-2 space-y-1">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('web.register.attempt') }}" method="POST" class="js-theme-boundary-form space-y-4"
              autocomplete="off">
            @csrf
            <input type="hidden" name="pending_theme" value="">

            <div>
                <label for="name" class="block font-medium text-sm text-slate-300 mb-1">Nombre</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus
                       autocomplete="off"
                       class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-hidden">
            </div>

            <div>
                <label for="email" class="block font-medium text-sm text-slate-300 mb-1">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required
                       autocomplete="off"
                       class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-hidden">
            </div>

            <div>
                <label for="password" class="block font-medium text-sm text-slate-300 mb-1">Contraseña</label>
                <input type="password" name="password" id="password" required
                       autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-hidden">
                <p class="text-xs text-slate-500 mt-1">
                    Mínimo 8 caracteres, con al menos una mayúscula, una minúscula, un número y un símbolo.
                </p>
            </div>

            <div>
                <label for="password_confirmation" class="block font-medium text-sm text-slate-300 mb-1">Confirmar
                    contraseña</label>
                <input type="password" name="password_confirmation" id="password_confirmation" required
                       autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-hidden">
            </div>

            <p class="text-xs text-slate-500">
                Por seguridad, tras registrarte te pediremos un código de verificación por email. Podrás
                desactivarlo cuando quieras desde Ajustes una vez dentro.
            </p>

            <button type="submit"
                    class="w-full bg-indigo-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors mt-2">
                Crear cuenta
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-400">
            ¿Ya tienes cuenta?
            <a href="{{ route('login') }}" class="text-indigo-400 hover:text-indigo-300 transition-colors">
                Inicia sesión
            </a>
        </p>
    </div>
</div>

</body>
</html>
