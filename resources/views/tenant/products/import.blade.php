<x-app-layout :title="__('Import products')">
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">{{ __('Catalog') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Import products') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Upload a CSV to queue product creation in the background.') }}</p>
            </div>
            <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                <x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-180" />
                {{ __('Back to products') }}
            </a>
        </div>
    </x-slot>

    <section class="surface-card sm:p-6">
        <x-form-error-summary :errors="$errors" :id-map="['file' => 'file']" />

        <form
            method="POST"
            action="{{ route('products.import.store') }}"
            enctype="multipart/form-data"
            class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            x-data="{
                dragging: false,
                fileName: '',
                setFile(files) {
                    if (! files || files.length === 0) {
                        return;
                    }

                    this.$refs.fileInput.files = files;
                    this.fileName = files[0].name;
                },
            }"
        >
            @csrf
            <div class="col-span-full min-w-0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label for="file" class="field-label">{{ __('CSV file') }}</label>
                    <a href="{{ route('products.import.example') }}" class="inline-flex items-center gap-1.5 rounded-md border border-line bg-muted px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-primary/10 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                        {{ __('Download example CSV') }}
                    </a>
                </div>

                <div
                    @dragenter.prevent="dragging = true"
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="if (! $el.contains($event.relatedTarget)) { dragging = false }"
                    @drop.prevent="dragging = false; setFile($event.dataTransfer.files)"
                    :class="dragging ? 'border-primary bg-primary/5' : 'border-line bg-muted/20'"
                    class="mt-2 rounded-xl border border-dashed p-6 transition-colors focus-within:ring-2 focus-within:ring-primary"
                >
                    <input
                        id="file"
                        x-ref="fileInput"
                        name="file"
                        type="file"
                        accept=".csv,text/csv"
                        class="sr-only"
                        required
                        @change="setFile($refs.fileInput.files)"
                    >
                    <div class="flex flex-col items-center gap-3 text-center">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <x-icon name="clipboard" class="h-5 w-5" />
                        </div>
                        <p class="text-sm font-medium text-foreground">
                            <span x-show="! fileName">{{ __('Drag and drop your CSV here') }}</span>
                            <span x-show="fileName" x-text="fileName" style="display: none;"></span>
                        </p>
                        <p class="text-xs text-muted-foreground">{{ __('or choose a file from your device') }}</p>
                        <button type="button" @click="$refs.fileInput.click()" class="btn-secondary">{{ __('Choose CSV') }}</button>
                        <p x-show="fileName" style="display: none;" class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">{{ __('File ready') }}</p>
                    </div>
                </div>

                <p id="file-help" class="mt-2 text-xs leading-5 text-muted-foreground">
                    {{ __('Required columns: sku, name. Optional: description, category, status, barcode, title, variant_attributes, low_stock_threshold, buffer_stock. Maximum size: 10 MB.') }}
                </p>
                <x-input-error :messages="$errors->get('file')" />
            </div>
            <div class="col-span-full flex flex-wrap items-center gap-3 border-t border-line pt-4">
                <button type="submit" class="btn-primary">{{ __('Queue import') }}</button>
                <a href="{{ route('products.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </section>
</x-app-layout>
