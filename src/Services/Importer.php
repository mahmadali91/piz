<?php
declare(strict_types=1);

namespace PIZ\Services;

use PIZ\Database;
use PIZ\Models\Product;
use PIZ\Settings;

final class Importer
{
    /**
     * Импорт товаров из .xlsx или .csv.
     *
     * @return array{added: int, updated: int, skipped: int, errors: string[]}
     */
    public static function importFile(string $path, string $filename, ?int $userId, array $options = []): array
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === 'xlsx') {
            $rows = ExcelReader::read($path);
        } elseif (in_array($extension, ['csv', 'txt'], true)) {
            $rows = self::readCsv($path);
        } elseif ($extension === 'xls') {
            throw new \RuntimeException('Старый формат .xls не поддерживается. Откройте файл в Excel и сохраните как .xlsx или .csv.');
        } else {
            throw new \RuntimeException('Поддерживаются файлы .xlsx и .csv.');
        }

        if ($rows === []) {
            throw new \RuntimeException('Файл пустой — не найдено ни одной строки.');
        }

        return self::importRows($rows, $userId, $options);
    }

    /**
     * @param array<int, array<int, string>> $rows
     * @return array{added: int, updated: int, skipped: int, errors: string[]}
     */
    public static function importRows(array $rows, ?int $userId, array $options = []): array
    {
        $updatePrices = $options['update_prices'] ?? true;

        $headerIndex = null;
        $map = [];
        foreach (array_slice($rows, 0, 15, true) as $index => $row) {
            $candidate = self::mapHeader($row);
            if (isset($candidate['name'], $candidate['sale'])) {
                $headerIndex = (int) $index;
                $map = $candidate;
                break;
            }
        }
        if ($headerIndex === null) {
            $map = ['name' => 0, 'purchase' => 1, 'sale' => 2];
        }

        $existing = Product::byNameMap();
        $defaultLow = Product::defaultLowStock();
        $defaultCategory = 'Без категории';
        $added = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                if ($headerIndex !== null && $index <= $headerIndex) {
                    continue;
                }
                $rowNumber = $index + 1;
                $name = trim((string) ($row[$map['name'] ?? 0] ?? ''));
                if ($name === '') {
                    $skipped++;
                    continue;
                }
                $normalized = Product::normalizeName($name);
                if (str_starts_with($normalized, 'итого') || str_contains($normalized, 'зачеркнут')) {
                    $skipped++;
                    continue;
                }

                $purchase = isset($map['purchase']) ? parse_money($row[$map['purchase']] ?? '') : null;
                $sale = parse_money($row[$map['sale']] ?? '');
                if ($sale === null || $sale <= 0) {
                    $errors[] = 'Строка ' . $rowNumber . ': не указана цена продажи — «' . $name . '».';
                    $skipped++;
                    continue;
                }
                if ($purchase !== null && $purchase < 0) {
                    $purchase = null;
                }

                $stock = null;
                if (isset($map['stock'])) {
                    $rawStock = trim((string) ($row[$map['stock']] ?? ''));
                    if ($rawStock !== '' && preg_match('/^-?\d+(?:[.,]\d+)?$/', $rawStock)) {
                        $stock = max(0, (int) round((float) str_replace(',', '.', $rawStock)));
                    }
                }

                $category = isset($map['category']) ? trim((string) ($row[$map['category']] ?? '')) : '';
                $category = $category !== '' ? mb_substr($category, 0, 80) : $defaultCategory;
                $unit = isset($map['unit']) ? trim((string) ($row[$map['unit']] ?? '')) : '';
                $unit = $unit !== '' ? mb_substr($unit, 0, 16) : 'шт';
                $sku = isset($map['sku']) ? trim((string) ($row[$map['sku']] ?? '')) : '';
                $sku = $sku !== '' ? mb_substr($sku, 0, 32) : null;
                $low = null;
                if (isset($map['low'])) {
                    $rawLow = trim((string) ($row[$map['low']] ?? ''));
                    if ($rawLow !== '' && preg_match('/^\d+$/', $rawLow)) {
                        $low = (int) $rawLow;
                    }
                }

                $existingProduct = $existing[$normalized] ?? null;

                if ($existingProduct) {
                    $sets = ['updated_at = ?'];
                    $params = [now()];
                    if ($updatePrices && $sale > 0) {
                        $sets[] = 'sale_price_kop = ?';
                        $params[] = $sale;
                    }
                    if ($updatePrices && $purchase !== null && $purchase > 0) {
                        $sets[] = 'purchase_price_kop = ?';
                        $params[] = $purchase;
                    }
                    if (isset($map['category']) && $category !== $defaultCategory) {
                        $sets[] = 'category = ?';
                        $params[] = $category;
                    }
                    if (isset($map['unit']) && $unit !== 'шт') {
                        $sets[] = 'unit = ?';
                        $params[] = $unit;
                    }
                    if ($sku !== null) {
                        $sets[] = 'sku = ?';
                        $params[] = $sku;
                    }
                    if ($low !== null) {
                        $sets[] = 'low_stock = ?';
                        $params[] = $low;
                    }
                    if ($stock !== null) {
                        $sets[] = 'stock_qty = ?';
                        $params[] = $stock;
                    }
                    $params[] = (int) $existingProduct['id'];
                    Database::query('UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);

                    if ($stock !== null && $stock !== (int) $existingProduct['stock_qty']) {
                        Database::query(
                            'INSERT INTO stock_movements (product_id, type, qty_delta, qty_after, unit_cost_kop, reason, user_id, created_at)
                             VALUES (?, "adjust", ?, ?, ?, ?, ?, ?)',
                            [
                                (int) $existingProduct['id'],
                                $stock - (int) $existingProduct['stock_qty'],
                                $stock,
                                $purchase ?? (int) $existingProduct['purchase_price_kop'],
                                'Импорт из файла',
                                $userId,
                                now(),
                            ]
                        );
                    }
                    $updated++;
                    $existing[$normalized]['stock_qty'] = $stock ?? $existingProduct['stock_qty'];
                    $existing[$normalized]['sale_price_kop'] = $updatePrices ? $sale : $existingProduct['sale_price_kop'];
                } else {
                    Database::query(
                        'INSERT INTO products (sku, name, category, purchase_price_kop, sale_price_kop, stock_qty, unit, low_stock, active, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
                        [
                            $sku,
                            mb_substr($name, 0, 160),
                            $category,
                            $purchase ?? 0,
                            $sale,
                            $stock ?? 0,
                            $unit,
                            $low ?? $defaultLow,
                            now(),
                            now(),
                        ]
                    );
                    $productId = (int) $pdo->lastInsertId();
                    if ($sku === null) {
                        $sku = 'P' . str_pad((string) $productId, 4, '0', STR_PAD_LEFT);
                        Database::query('UPDATE products SET sku = ? WHERE id = ?', [$sku, $productId]);
                    }
                    if (($stock ?? 0) > 0) {
                        Database::query(
                            'INSERT INTO stock_movements (product_id, type, qty_delta, qty_after, unit_cost_kop, reason, user_id, created_at)
                             VALUES (?, "initial", ?, ?, ?, ?, ?, ?)',
                            [$productId, $stock, $stock, $purchase ?? 0, 'Импорт из файла', $userId, now()]
                        );
                    }
                    $existing[$normalized] = [
                        'id' => $productId,
                        'name' => $name,
                        'stock_qty' => $stock ?? 0,
                        'sale_price_kop' => $sale,
                        'purchase_price_kop' => $purchase ?? 0,
                    ];
                    $added++;
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param array<int, string> $row
     * @return array<string, int>
     */
    private static function mapHeader(array $row): array
    {
        $map = [];
        foreach ($row as $index => $cell) {
            $label = mb_strtolower(trim((string) $cell));
            $label = preg_replace('/\s+/u', ' ', $label) ?? $label;
            if ($label === '') {
                continue;
            }
            if (str_contains($label, 'наимен') || str_contains($label, 'назван') || str_contains($label, 'товар')) {
                $map['name'] ??= $index;
            } elseif (str_contains($label, 'артикул') || str_contains($label, 'код')) {
                $map['sku'] ??= $index;
            } elseif (str_contains($label, 'закуп') || str_contains($label, 'себестоим') || str_contains($label, 'приход')) {
                $map['purchase'] ??= $index;
            } elseif (str_contains($label, 'продаж') || str_contains($label, 'розниц') || str_contains($label, 'цена')) {
                $map['sale'] ??= $index;
            } elseif (str_contains($label, 'мин') && str_contains($label, 'остат')) {
                $map['low'] ??= $index;
            } elseif (str_contains($label, 'остат') || str_contains($label, 'колич') || str_contains($label, 'кол-во')) {
                $map['stock'] ??= $index;
            } elseif (str_contains($label, 'категор')) {
                $map['category'] ??= $index;
            } elseif (str_contains($label, 'ед.') || str_contains($label, 'единиц')) {
                $map['unit'] ??= $index;
            }
        }
        return $map;
    }

    /** @return array<int, array<int, string>> */
    private static function readCsv(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Не удалось прочитать файл.');
        }
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1251');
        }
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);

        $sample = '';
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $sample = $line;
                break;
            }
        }
        $counts = [';' => substr_count($sample, ';'), ',' => substr_count($sample, ','), "\t" => substr_count($sample, "\t")];
        arsort($counts);
        $delimiter = (string) array_key_first($counts);
        if ($delimiter === '') {
            $delimiter = ';';
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter, '"', '\\');
            $rows[] = array_map(static fn ($value) => trim((string) $value), $cells);
        }
        return $rows;
    }

    /** Загружает стартовый каталог из data/products.csv (только если товаров ещё нет). */
    public static function seedCatalog(): ?array
    {
        $count = (int) Database::fetchValue('SELECT COUNT(*) FROM products');
        if ($count > 0) {
            return null;
        }
        $file = PIZ_ROOT . '/data/products.csv';
        if (!is_file($file)) {
            return null;
        }
        $result = self::importFile($file, 'products.csv', null, ['update_prices' => true]);
        if ($result['added'] > 0) {
            Settings::set('seeded_at', now());
        }
        return $result;
    }
}
