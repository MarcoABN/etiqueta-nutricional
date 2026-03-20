<div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-5 flex flex-col h-full">
    <h3 class="text-base font-semibold text-gray-950 dark:text-white mb-4 text-center">Eventos do Mês</h3>

    {{-- Lista de Eventos --}}
    <div class="flex-1">
        @if(count($items) > 0)
            <div class="space-y-4">
                @foreach($items as $item)
                    <div class="flex items-start gap-3">
                        
                        {{-- Círculo de Status --}}
                        <div 
                            class="w-3 h-3 rounded-full shrink-0 mt-1.5 shadow-sm border border-black/10 dark:border-white/10" 
                            style="background-color: {{ $item['color'] }};"
                        ></div>

                        {{-- Texto da Etapa --}}
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200 whitespace-normal break-words leading-snug">
                                {{ $item['title'] }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ $item['date']->format('d/m/Y') }}
                            </p>
                        </div>

                    </div>
                @endforeach
            </div>
        @else
            <div class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Nenhum evento programado para este período.
            </div>
        @endif
    </div>

    {{-- Legenda de Cores --}}
    <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800">
        <div class="flex flex-wrap justify-center gap-x-4 gap-y-3">
            <div class="flex items-center gap-1.5">
                <div class="w-3 h-3 rounded-full shrink-0 shadow-sm border border-black/10 dark:border-white/10" style="background-color: #3b82f6;"></div>
                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Aberto</span>
            </div>
            <div class="flex items-center gap-1.5">
                <div class="w-3 h-3 rounded-full shrink-0 shadow-sm border border-black/10 dark:border-white/10" style="background-color: #10b981;"></div>
                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Concluído</span>
            </div>
            <div class="flex items-center gap-1.5">
                <div class="w-3 h-3 rounded-full shrink-0 shadow-sm border border-black/10 dark:border-white/10" style="background-color: #ef4444;"></div>
                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Em atraso</span>
            </div>
        </div>
    </div>
</div>