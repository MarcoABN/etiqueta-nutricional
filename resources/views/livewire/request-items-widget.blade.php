<div>
    {{-- Área de Inserção (Fixo) --}}
    <div class="mb-2">
        {{ $this->form }}
    </div>

    {{-- Área de Listagem (Scroll Independente) --}}
    <div 
        class="border rounded-xl bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700" 
        style="max-height: 450px; overflow-y: auto;"
    >
        {{ $this->table }}
    </div>
</div>