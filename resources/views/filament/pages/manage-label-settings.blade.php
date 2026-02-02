<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}
        
        <div class="mt-4 flex justify-end">
            <x-filament::button type="submit">
                Salvar Configurações
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>