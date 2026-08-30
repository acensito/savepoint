@include('partials.auth-head', ['title' => 'Verificación en dos pasos'])

    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
        <h1 class="text-lg font-bold text-slate-100 mb-1">Verificación en dos pasos</h1>
        <p class="text-slate-400 text-sm mb-6">
            Te hemos enviado un código a <strong class="text-slate-300">{{ $email }}</strong>. Caduca en 10 minutos.
        </p>

        @if (session('success'))
            <div
                class="mb-4 text-sm text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 rounded-lg px-4 py-2">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 text-sm text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-2">
                {{ session('error') }}
            </div>
        @endif

        @error('code')
        <div class="mb-4 text-sm text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-2">
            {{ $message }}
        </div>
        @enderror

        <form action="{{ route('two-factor.verify') }}" method="POST" class="js-theme-boundary-form space-y-5">
            @csrf
            <input type="hidden" name="pending_theme" value="">

            <div>
                <label for="code" class="block font-medium text-sm text-slate-300 mb-1">Código</label>
                <input type="text" name="code" id="code" inputmode="numeric" pattern="[0-9]*"
                       autocomplete="one-time-code"
                       maxlength="6" required autofocus
                       class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 tracking-[0.5em] text-center text-lg focus:border-indigo-500 focus:ring-indigo-500 outline-hidden">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-400">
                <input type="checkbox" name="trust_device" value="1"
                       class="rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                Recordar este dispositivo 30 días
            </label>

            <button type="submit"
                    class="w-full bg-indigo-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                Verificar
            </button>
        </form>

        <form action="{{ route('two-factor.resend') }}" method="POST" class="mt-4">
            @csrf
            <button type="submit"
                    class="w-full text-center text-sm text-indigo-400 hover:text-indigo-300 transition-colors">
                Reenviar código
            </button>
        </form>
    </div>
</div>

</body>
</html>
