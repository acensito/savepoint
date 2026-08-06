@props(['segments'])

<div>
    <div class="w-full h-3 rounded-full overflow-hidden bg-slate-800 flex gap-[2px]">
        @foreach($segments as $segment)
            @if($segment['total'] > 0)
                <div class="h-full first:rounded-l-full last:rounded-r-full"
                    style="width: {{ $segment['percent'] }}%; background-color: {{ $segment['color'] }}"
                    title="{{ $segment['label'] }}: {{ $segment['total'] }} ({{ $segment['percent'] }}%)"></div>
            @endif
        @endforeach
    </div>

    <div class="flex flex-wrap gap-x-5 gap-y-2 mt-4">
        @foreach($segments as $segment)
            @if($segment['total'] > 0)
                <div class="flex items-center gap-2 text-sm">
                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $segment['color'] }}"></span>
                    <span class="text-slate-300">{{ $segment['label'] }}</span>
                    <span class="text-slate-500 tabular-nums">{{ $segment['total'] }} ({{ $segment['percent'] }}%)</span>
                </div>
            @endif
        @endforeach
    </div>
</div>
