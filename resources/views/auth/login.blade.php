@include('partials.auth-head', ['title' => 'Acceder'])

    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
        <h1 class="text-lg font-bold text-slate-100 mb-1">Acceder</h1>
        <p class="text-slate-400 text-sm mb-6">Entra con tu cuenta para ver tu colección.</p>

        @if (session('error'))
            <div class="mb-4 text-sm text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-2">
                {{ session('error') }}
            </div>
        @endif

        @error('email')
        <div class="mb-4 text-sm text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-2">
            {{ $message }}
        </div>
        @enderror

        <form action="{{ route('web.login.attempt') }}" method="POST" class="js-theme-boundary-form space-y-5">
            @csrf
            <input type="hidden" name="pending_theme" value="">

            <div>
                <label for="email" class="block font-medium text-sm text-slate-300 mb-1">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-hidden">
            </div>

            <div>
                <label for="password" class="block font-medium text-sm text-slate-300 mb-1">Contraseña</label>
                <input type="password" name="password" id="password" required
                       class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-hidden">
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-slate-400">
                    <input type="checkbox" name="remember"
                           class="rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                    Recordarme
                </label>

                <a href="{{ route('password.request') }}"
                   class="text-sm text-indigo-400 hover:text-indigo-300 transition-colors">
                    ¿Olvidaste tu contraseña?
                </a>
            </div>

            <button type="submit"
                    class="w-full bg-indigo-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                Entrar
            </button>
        </form>

        @if($showDevCredentials && $devCredentials)
            <button type="button" id="fill-dev-credentials"
                    data-email="{{ $devCredentials['email'] }}"
                    data-password="{{ $devCredentials['password'] }}"
                    class="mt-4 w-full border border-slate-700 text-slate-300 px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors">
                Rellenar credenciales de desarrollo
            </button>

            <script nonce="{{ $cspNonce }}">
                const devCredentialsButton = document.getElementById('fill-dev-credentials');
                devCredentialsButton.addEventListener('click', function () {
                    document.getElementById('email').value = devCredentialsButton.dataset.email;
                    document.getElementById('password').value = devCredentialsButton.dataset.password;
                });
            </script>
        @endif

        @if($registrationEnabled)
            <p class="mt-6 text-center text-sm text-slate-400">
                ¿No tienes cuenta?
                <a href="{{ route('register') }}" class="text-indigo-400 hover:text-indigo-300 transition-colors">
                    Regístrate
                </a>
            </p>
        @endif
    </div>
</div>

</body>
</html>
