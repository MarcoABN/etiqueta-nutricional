<x-filament-panels::page>
    
    <div class="mb-4">
        {{ $this->form }}
    </div>

    <div class="bg-white shadow-sm border border-gray-200 rounded-xl dark:bg-gray-800 dark:border-gray-700 overflow-hidden">
        {{ $this->table }}
    </div>

    {{-- Iframe Oculto para Impressão Nativa do Navegador --}}
    <iframe id="printPalletFrame" src="" style="width:0;height:0;border:0;border:none;display:none;"></iframe>

    {{-- Listener Alpine para interceptar o evento Livewire e abrir a janela de impressão --}}
    <div x-data
         x-on:print-pallet-event.window="
            document.getElementById('printPalletFrame').src = $event.detail.url;
            new FilamentNotification().title('Preparando etiqueta do pallet...').info().send();
         ">
    </div>

</x-filament-panels::page>