<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportProducts implements ShouldQueue
{
    use Queueable;

    public const CSV_HEADERS = [
        'sku',
        'name',
        'description',
        'category',
        'status',
        'barcode',
        'title',
        'variant_attributes',
        'low_stock_threshold',
        'buffer_stock',
    ];

    public function __construct(
        private readonly string $filePath,
        private readonly string $tenantId,
        private readonly int $userId,
    ) {}

    public function handle(): void
    {
        tenancy()->initialize($this->tenantId);

        try {
            $contents = Storage::disk('local')->get($this->filePath);

            if ($contents === null) {
                return;
            }

            $handle = fopen('php://temp', 'r+');

            if ($handle === false) {
                return;
            }

            fwrite($handle, $contents);
            rewind($handle);

            $header = fgetcsv($handle);

            if ($header === false) {
                fclose($handle);
                Storage::disk('local')->delete($this->filePath);

                return;
            }

            $header = array_map(
                fn (mixed $column): string => Str::snake(trim((string) $column)),
                $header,
            );

            $chunk = [];

            while (($row = fgetcsv($handle)) !== false) {
                $row = array_pad(array_slice($row, 0, count($header)), count($header), '');
                $chunk[] = array_combine($header, $row);

                if (count($chunk) >= 50) {
                    $this->dispatchChunk($chunk);
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                $this->dispatchChunk($chunk);
            }

            fclose($handle);
            Storage::disk('local')->delete($this->filePath);
        } finally {
            tenancy()->end();
        }
    }

    /** @param list<array<string, string>> $rows */
    private function dispatchChunk(array $rows): void
    {
        ProcessProductImportChunk::dispatch($rows, $this->tenantId, $this->userId);
    }
}
