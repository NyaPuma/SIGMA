<?php

declare(strict_types=1);

namespace Tests\Feature\API\Controllers;

use App\Models\Part;
use App\Models\PartCategory;
use Tests\Base\FeatureTestCase;

final class StockReportDownloadFeatureTest extends FeatureTestCase
{
    public function test_admin_can_download_low_stock_csv(): void
    {
        $admin = $this->createAdmin();
        $part = Part::factory()->lowStock()->create([
            'sku' => 'LOW-001',
            'name' => 'Low stock test part',
        ]);

        $response = $this->withHeader('X-Auth-Token', $admin->api_token)
            ->actingAs($admin)
            ->get('/api/stock/reports/low-stock.csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString($part->sku, $content);
    }

    public function test_low_stock_csv_writes_the_category_name_not_the_column_label(): void
    {
        $admin = $this->createAdmin();
        $category = PartCategory::factory()->create(['name' => 'Fixações']);
        Part::factory()->lowStock()->create([
            'sku' => 'CAT-001',
            'part_category_id' => $category->id,
        ]);

        $content = $this->withHeader('X-Auth-Token', $admin->api_token)
            ->actingAs($admin)
            ->get('/api/stock/reports/low-stock.csv')
            ->streamedContent();

        // SQLite converte um identificador inexistente num literal de texto, por isso
        // um `select(['category_id'])` errado não rebenta aqui — escreve a própria
        // palavra "category_id" na coluna da categoria. Em MySQL/PostgreSQL é um 500.
        $this->assertStringContainsString('Fixações', $content);
        $this->assertStringNotContainsString('category_id', $content);
    }

    public function test_admin_can_download_inventory_csv(): void
    {
        $admin = $this->createAdmin();
        $part = Part::factory()->create([
            'sku' => 'INV-001',
            'name' => 'Inventory test part',
        ]);

        $response = $this->withHeader('X-Auth-Token', $admin->api_token)
            ->actingAs($admin)
            ->get('/api/stock/reports/inventory.csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString($part->sku, $content);
    }
}
