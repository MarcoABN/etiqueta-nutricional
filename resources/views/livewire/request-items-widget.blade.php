<div>
    <div class="mb-2">
        {{ $this->form }}
    </div>

    <div class="border rounded-xl bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700" style="max-height: 450px; overflow-y: auto;">
        {{ $this->table }}
    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('open-new-tab', (event) => {
                window.open(event.url, '_blank');
            });
        });
    </script>
</div>