<?php

namespace App\Actions\Channels;

use App\Adapters\ChannelAdapterResolver;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ImportChannelListings
{
    public function __construct(private ChannelAdapterResolver $resolver) {}

    public function __invoke(ChannelConnection $connection): int
    {
        $connection->load('credentials');

        try {
            $listings = $this->resolver
                ->resolve($connection->provider)
                ->fetchListings($connection->decryptedCredentials());
        } catch (ConnectionException|RequestException|InvalidArgumentException) {
            throw ValidationException::withMessages([
                'connection' => __('Channel listings could not be imported.'),
            ]);
        }

        $imported = DB::transaction(function () use ($connection, $listings): int {
            foreach ($listings as $listing) {
                ChannelListing::updateOrCreate(
                    [
                        'channel_connection_id' => $connection->id,
                        'external_id' => $listing['external_id'],
                    ],
                    [
                        'title' => $listing['title'],
                        'status' => $this->normalizeStatus($listing['status']),
                        'last_imported_at' => now(),
                    ],
                );
            }

            return count($listings);
        });

        activity('channels')
            ->performedOn($connection)
            ->causedBy(auth()->user())
            ->event('listings_imported')
            ->log("imported {$imported} listing(s)");

        return $imported;
    }

    private function normalizeStatus(string $status): string
    {
        return in_array(mb_strtolower($status), ['active', 'publish'], true)
            ? ChannelListing::STATUS_ACTIVE
            : ChannelListing::STATUS_ARCHIVED;
    }
}
