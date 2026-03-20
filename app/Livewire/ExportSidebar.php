<?php

namespace App\Livewire;

use App\Models\ShipmentStep;
use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;

class ExportSidebar extends Component
{
    public array $eventsByShipment = [];
    public string $monthName = '';

    // Este atributo faz o componente escutar o evento disparado pelo calendário
    #[On('updateCalendarDates')]
    public function updateDates($start, $end)
    {
        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);
        
        // O calendário passa as datas do 'grid' (inclui dias do mês anterior/próximo).
        // Pegamos o dia do meio para saber exatamente qual mês está sendo exibido.
        $middleDate = $startDate->copy()->addDays($startDate->diffInDays($endDate) / 2);
        $this->monthName = $middleDate->translatedFormat('F / Y'); // Ex: março / 2026

        // Busca as etapas do período e agrupa pelo nome da remessa
        $steps = ShipmentStep::with('shipment')
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->orderBy('scheduled_date', 'asc')
            ->get();

        $this->eventsByShipment = $steps->groupBy(function($step) {
            return $step->shipment->name ?? 'Sem Remessa';
        })->toArray();
    }

    public function render()
    {
        return view('livewire.export-sidebar');
    }
}