<x-filament-panels::page>
    
    {{-- Bloco Superior: Formulário de Filtro --}}
    <div class="mb-4">
        {{ $this->form }}
    </div>

    {{-- Bloco Inferior: Listagem da Tabela --}}
    <div class="bg-white shadow-sm border border-gray-200 rounded-xl dark:bg-gray-800 dark:border-gray-700 overflow-hidden">
        {{ $this->table }}
    </div>

    {{-- Iframe Oculto para Impressão Nativa do Navegador --}}
    <iframe id="printBatchFrame" src="" style="width:0;height:0;border:0;border:none;display:none;"></iframe>

    {{-- Script para receber o comando do Filament e abrir a impressão --}}
    <div x-data
         x-on:print-label-event.window="
            document.getElementById('printBatchFrame').src = $event.detail.url;
            new FilamentNotification().title('Preparando impressão...').info().send();
         ">
    </div>

</x-filament-panels::page>