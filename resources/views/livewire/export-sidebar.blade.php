<x-filament::section>
    <x-slot name="heading">
        <div class="flex items-center gap-2">
            <x-heroicon-o-list-bullet class="w-6 h-6 text-primary-500" />
            <span class="capitalize">Eventos de {{ $monthName ?: 'Mês Atual' }}</span>
        </div>
    </x-slot>

    <div class="flex flex-col gap-4">
        @forelse($eventsByShipment as $shipmentName => $steps)
            <div>
                <h4 class="font-bold text-sm text-gray-700 dark:text-gray-300 mb-2 border-b pb-1 dark:border-gray-800">
                    {{ $shipmentName }}
                </h4>
                <ul class="flex flex-col gap-2">
                    @foreach($steps as $step)
                        @php
                            $isLate = !$step['is_completed'] && \Carbon\Carbon::parse($step['scheduled_date'])->isPast();
                            $colorClass = $step['is_completed'] 
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30' 
                                : ($isLate ? 'bg-red-50 text-red-700 dark:bg-red-900/30' : 'bg-blue-50 text-blue-700 dark:bg-blue-900/30');
                        @endphp
                        
                        <li class="flex items-center gap-2 text-xs p-2 rounded-md {{ $colorClass }}">
                            <span class="font-bold shrink-0">{{ \Carbon\Carbon::parse($step['scheduled_date'])->format('d/m') }}</span>
                            <span class="text-gray-400">-</span>
                            <span class="truncate" title="{{ $step['name'] }}">{{ $step['name'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-sm text-gray-500 text-center py-4">Nenhuma remessa agendada para este mês.</p>
        @endforelse
    </div>
</x-filament::section>