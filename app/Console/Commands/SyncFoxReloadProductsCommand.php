<?php

namespace App\Console\Commands;

use App\Services\Supplier\SupplierCatalogSyncService;
use Illuminate\Console\Command;

class SyncFoxReloadProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supplier:foxreload-sync {--brand= : Optional brand key to sync (e.g. pubg, brawl_stars)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync curated digital goods and gift cards from FoxReload API into the local catalog';

    /**
     * Execute the console command.
     */
    public function handle(SupplierCatalogSyncService $syncService): int
    {
        $this->info("🚀 Starting FoxReload Catalog Sync...");

        $brandFilter = $this->option('brand') ? [$this->option('brand')] : null;

        $stats = $syncService->syncCuratedBrands($brandFilter);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Categories Created', $stats['categories_created']],
                ['Brands Created', $stats['brands_created']],
                ['Products Newly Imported', $stats['products_imported']],
                ['Products Updated', $stats['products_updated']],
                ['Errors Count', count($stats['errors'])],
            ]
        );

        if (!empty($stats['errors'])) {
            $this->error("Some errors occurred during sync:");
            foreach ($stats['errors'] as $error) {
                $this->line(" - <fg=red>{$error}</>");
            }
        }

        $this->info("✅ FoxReload Catalog Sync completed successfully!");
        return Command::SUCCESS;
    }
}
