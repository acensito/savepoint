@include('partials.auth-head', ['title' => 'Recuperar contraseña'])

    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
        <h1 class="text-lg font-bold text-slate-100 mb-1">Recuperar contraseña</h1>
        <p class="text-slate-400 text-sm mb-6">Te enviaremos un enlace para restablecerla.</p>

        @if(session('success'))
            <div
                class="mb-4 text-sm text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 rounded-lg px-4 py-2">
                {{ session('success') }}
            </div>
        @endif

        @error('email')
        <div class="mb-4 text-sm text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-2">
            {{ $message }}
        </div>
        @enderror

        <form action="{{ route('password.email') }}" method="POST" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="block font-medium text-sm text-slate-300 mb-1">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-hidden">
            </div>

            <button type="submit"
                    class="w-full bg-indigo-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                Enviar enlace
            </button>
        </form>

        <a href="{{ route('login') }}"
           class="block text-center text-sm text-slate-400 hover:text-slate-100 mt-6 transition-colors">
            ← Volver a acceder
        </a>
    </div>
</div>

</body>
</html>
