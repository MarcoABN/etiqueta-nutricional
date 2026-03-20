<x-filament-panels::page>
    <x-filament::grid default="1" md="4" class="gap-6">
        
        <x-filament::grid.column md="3">
            @livewire(\App\Filament\Widgets\ExportCalendarWidget::class)
        </x-filament::grid.column>

        <x-filament::grid.column md="1" class="flex flex-col gap-4">
            
            <x-filament::button href="{{ \App\Filament\Resources\ShipmentResource::getUrl('index') }}" tag="a" icon="heroicon-o-truck" color="primary" class="w-full">
                Cadastro Completo de Remessas
            </x-filament::button>

            <livewire:export-sidebar />

            <x-filament::section>
                <x-slot name="heading">Legenda</x-slot>
                
                <div class="flex flex-col gap-3 mt-2">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                        <span>Etapas Pendentes</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <span class="w-3 h-3 rounded-full bg-red-500"></span>
                        <span>Atrasados</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                        <span>Concluídos</span>
                    </div>
                </div>
            </x-filament::section>

        </x-filament::grid.column>

    </x-filament::grid>
</x-filament-panels::page>