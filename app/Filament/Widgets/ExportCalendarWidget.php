<?php

namespace App\Filament\Widgets;

use App\Models\Demand;
use App\Models\ShipmentStep;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class ExportCalendarWidget extends FullCalendarWidget
{
    public Model | string | null $model = ShipmentStep::class;

    public static function canView(): bool
    {
        return request()->routeIs('*export-schedule*');
    }

    public function config(): array
    {
        return [
            'locale' => 'pt-br',
            'height' => 500,
            'headerToolbar' => [
                'left'   => 'prev,next today',
                'center' => 'title',
                'right'  => 'dayGridMonth,timeGridWeek,listWeek',
            ],
            'selectable'   => true,
            'selectMirror' => true,
        ];
    }

    public function onDateSelect(string $start, ?string $end, bool $allDay, ?array $view, ?array $resource): void
    {
        session(['calendar_selected_date' => substr($start, 0, 10)]);

        $this->mountAction('nova_etapa');
    }

    public function resolveRecord(string | int $key): Model
    {
        $stringKey = (string) $key;

        if (str_starts_with($stringKey, 'step_')) {
            return ShipmentStep::findOrFail((int) str_replace('step_', '', $stringKey));
        }

        return new ShipmentStep();
    }

    protected function headerActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make('nova_etapa')
                ->model(ShipmentStep::class)
                ->label('Nova Etapa')
                ->icon('heroicon-o-plus')
                ->modalHeading('Adicionar Etapa Rápida')
                ->form([
                    \Filament\Forms\Components\Select::make('shipment_id')
                        ->label('Vincular à Remessa')
                        ->relationship('shipment', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->createOptionForm([
                            \Filament\Forms\Components\TextInput::make('name')
                                ->label('Nome da Nova Remessa')
                                ->required(),
                        ]),

                    \Filament\Forms\Components\TextInput::make('name')
                        ->label('Nome da Etapa')
                        ->placeholder('Ex: Frete Aéreo, Alfândega...')
                        ->required(),

                    \Filament\Forms\Components\DatePicker::make('scheduled_date')
                        ->label('Data Prevista')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->firstDayOfWeek(0) // <-- INICIA NO DOMINGO
                        ->dehydrated()
                        ->required()
                        ->default(fn() => session('calendar_selected_date', now()->format('Y-m-d'))),
                ])
                ->after(function () {
                    session()->forget('calendar_selected_date');
                    $this->refreshRecords();
                })
                ->successNotificationTitle('Etapa adicionada ao cronograma!'),
        ];
    }

    protected function viewAction(): \Filament\Actions\Action
    {
        return \Saade\FilamentFullCalendar\Actions\EditAction::make('view')
            ->modalHeading('Gerenciar Etapa')
            ->form([
                \Filament\Forms\Components\Select::make('shipment_id')
                    ->label('Remessa')
                    ->relationship('shipment', 'name')
                    ->disabled()
                    ->required(),
                \Filament\Forms\Components\TextInput::make('name')
                    ->label('Nome da Etapa')
                    ->required(),
                \Filament\Forms\Components\DatePicker::make('scheduled_date')
                    ->label('Data Prevista')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->firstDayOfWeek(0) // <-- INICIA NO DOMINGO
                    ->required(),
                \Filament\Forms\Components\Toggle::make('is_completed')
                    ->label('Marcar etapa como Concluída')
                    ->onColor('success')
                    ->offColor('danger')
                    ->inline(false),
            ])
            ->extraModalFooterActions(fn(\Filament\Actions\Action $action): array => [
                \Filament\Actions\DeleteAction::make('excluir_etapa')
                    ->record($action->getRecord())
                    ->cancelParentActions()
                    ->requiresConfirmation()
                    ->modalHeading('Excluir Etapa')
                    ->modalDescription('Tem certeza que deseja remover esta etapa do cronograma?')
                    ->successNotificationTitle('Etapa removida com sucesso!')
                    ->after(fn() => $this->refreshRecords()),
            ])
            ->successNotificationTitle('Etapa atualizada com sucesso!')
            ->after(fn() => $this->refreshRecords());
    }

    public function fetchEvents(array $fetchInfo): array
    {
        $start = Carbon::parse($fetchInfo['start']);
        $end   = Carbon::parse($fetchInfo['end']);

        $this->dispatch('updateCalendarDates', start: $start->toDateString(), end: $end->toDateString());

        $events = [];

        $year     = $start->format('Y');
        $holidays = Cache::remember("feriados_brasil_{$year}", now()->addDays(30), function () use ($year) {
            $response = Http::get("https://brasilapi.com.br/api/feriados/v1/{$year}");
            return $response->successful() ? $response->json() : [];
        });

        foreach ($holidays as $holiday) {
            $events[] = [
                'id'        => 'holiday_' . $holiday['date'],
                'title'     => $holiday['name'],
                'start'     => $holiday['date'],
                'allDay'    => true,
                'color'     => '#fef08a',
                'textColor' => '#854d0e',
                'display'   => 'background',
            ];
        }

        $steps = ShipmentStep::with('shipment')
            ->whereBetween('scheduled_date', [$start, $end])
            ->get();

        foreach ($steps as $step) {
            $isLate   = !$step->is_completed && $step->scheduled_date->isPast();
            $events[] = [
                'id'    => 'step_' . $step->id,
                'title' => "{$step->shipment->name} - {$step->name}",
                'start' => $step->scheduled_date->format('Y-m-d'),
                'color' => $step->is_completed ? '#10b981' : ($isLate ? '#ef4444' : '#3b82f6'),
            ];
        }

        $demands = Demand::whereNotNull('deadline')
            ->whereBetween('deadline', [$start, $end])
            ->get();

        foreach ($demands as $demand) {
            $isLate   = $demand->status !== 'finished' && Carbon::parse($demand->deadline)->isPast();
            $events[] = [
                'id'    => 'demand_' . $demand->id,
                'title' => "Tarefa: {$demand->title}",
                'start' => Carbon::parse($demand->deadline)->format('Y-m-d'),
                'color' => $demand->status === 'finished' ? '#10b981' : ($isLate ? '#ef4444' : '#6b7280'),
                'url'   => \App\Filament\Resources\DemandResource::getUrl('edit', ['record' => $demand->id]),
            ];
        }

        return $events;
    }
}
