<?php

namespace App\Livewire;

use App\Models\Demand;
use App\Models\ShipmentStep;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\On;

class ExportSidebar extends Component
{
    public $start;
    public $end;

    #[On('updateCalendarDates')]
    public function updateDates($start, $end)
    {
        $this->start = $start;
        $this->end = $end;
    }

    public function getItemsProperty()
    {
        if (!$this->start || !$this->end) {
            return [];
        }

        $items = collect();
        $start = Carbon::parse($this->start);
        $end = Carbon::parse($this->end);

        // Busca as Etapas
        $steps = ShipmentStep::with('shipment')
            ->whereBetween('scheduled_date', [$start, $end])
            ->get();

        foreach ($steps as $step) {
            $isLate = !$step->is_completed && $step->scheduled_date->isPast();
            $items->push([
                'title' => "{$step->shipment->name} - {$step->name}",
                'date'  => $step->scheduled_date,
                // Reflete a mesma cor usada no calendário
                'color' => $step->is_completed ? '#10b981' : ($isLate ? '#ef4444' : '#3b82f6'),
            ]);
        }

        // Busca as Tarefas (Demands)
        $demands = Demand::whereNotNull('deadline')
            ->whereBetween('deadline', [$start, $end])
            ->get();

        foreach ($demands as $demand) {
            $isLate = $demand->status !== 'finished' && Carbon::parse($demand->deadline)->isPast();
            $items->push([
                'title' => "Tarefa: {$demand->title}",
                'date'  => Carbon::parse($demand->deadline),
                // Tarefas usam cinza quando estão em aberto
                'color' => $demand->status === 'finished' ? '#10b981' : ($isLate ? '#ef4444' : '#6b7280'),
            ]);
        }

        // Retorna tudo unificado e ordenado pela data mais próxima
        return $items->sortBy('date')->values()->all();
    }

    public function render()
    {
        return view('livewire.export-sidebar', [
            'items' => $this->items,
        ]);
    }
}
