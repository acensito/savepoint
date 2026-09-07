<!-- Lista de sugerencias de CEX + ficha de comprobación: parcial compartido
     entre las dos ramas de _quick-search-results.blade.php que pueden ofrecer
     sugerencias externas (sin coincidencia local, o con coincidencias locales
     y CEX como complemento, ver #108) — nunca se pintan las dos ramas a la
     vez, así que reutilizar los mismos ids (#cex-results-list/#cex-preview)
     no genera duplicados en el DOM. Cada botón lleva sus datos en data-* para
     que la ficha de comprobación se pinte sin otra petición (ver
     initExternalResultPreview en app.js). -->
<ul id="cex-results-list" class="pb-2">
    @foreach($externalResults as $result)
        <li>
            <button type="button" class="js-cex-result w-full flex items-center gap-3 px-4 py-2.5 hover:bg-slate-800 transition-colors text-left"
                data-title="{{ $result->title }}" data-ean="{{ $result->ean }}" data-cover="{{ $result->coverUrl }}" data-platform="{{ $result->platform }}">
                @if($result->coverUrl)
                    <img src="{{ $result->coverUrl }}" alt="" class="w-10 h-10 object-cover rounded-lg border border-slate-700 shrink-0">
                @else
                    <div class="w-10 h-10 rounded-lg bg-slate-800 border border-slate-700 shrink-0"></div>
                @endif
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-slate-100 truncate">{{ $result->title }}</div>
                    <div class="flex items-center gap-2 mt-0.5">
                        @if($result->platform)
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 rounded px-1.5 py-0.5">{{ $result->platform }}</span>
                        @endif
                        @if($result->ean)
                            <span class="text-xs text-slate-500">EAN {{ $result->ean }}</span>
                        @endif
                    </div>
                </div>
                <x-gicon name="chevron_right" class="text-[16px] text-slate-600 shrink-0" />
            </button>
        </li>
    @endforeach
</ul>

<!-- Ficha de comprobación: oculta hasta que se pulsa un resultado de arriba.
     Sus datos se rellenan por JS desde el data-* del botón pulsado, no desde
     otra petición al servidor. -->
<div id="cex-preview" data-create-url="{{ route('web.games.create') }}" class="hidden px-4 py-4 border-t border-slate-800">
    <button type="button" class="js-cex-preview-back flex items-center gap-1 text-xs text-slate-500 hover:text-slate-300 mb-3">
        <x-gicon name="arrow_back" class="text-[14px]" />
        Volver a los resultados
    </button>
    <div class="flex items-start gap-4">
        <img id="cex-preview-cover" src="" alt="" class="hidden w-20 h-auto rounded-xl border border-slate-700 shrink-0">
        <div class="flex-1 min-w-0">
            <div id="cex-preview-title" class="text-base font-semibold text-slate-100"></div>
            <div class="flex items-center gap-2 mt-1">
                <span id="cex-preview-platform" class="hidden text-[10px] font-semibold uppercase tracking-wide text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 rounded px-1.5 py-0.5"></span>
                <div id="cex-preview-ean" class="text-sm text-slate-500"></div>
            </div>
            <p class="text-xs text-slate-500 mt-2">Comprueba que los datos coinciden antes de darlo de alta: la búsqueda es de CEX, no de tu colección.</p>
        </div>
    </div>
    <a id="cex-preview-add-link" href="#" class="mt-4 inline-flex items-center gap-1.5 bg-(--color-navbar) text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-(--color-navbar-hover) transition-colors">
        <x-gicon name="add_circle" class="text-[16px]" />
        Dar de alta
    </a>
</div>
