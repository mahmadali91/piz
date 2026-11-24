<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use PIZ\Database;

$map = [
    'Бон аква 1 л' => 'Вода',
    'Бон аква 0.5' => 'Вода',
    'Нифазон' => 'Вода',
    'Нива' => 'Вода',
    'Наша 033' => 'Вода',
    'Вексы' => 'Напитки',
    'Ёмса' => 'Напитки',
    'Кало ос.' => 'Напитки',
    'Кала 1 литр' => 'Напитки',
    'Чарила' => 'Соки',
    'Сиб. жемчуг' => 'Соки',
    'Карио' => 'Соки',
    'Салиса сладкий' => 'Соки',
    'Салиса Тангир' => 'Соки',
    'Нами ос.' => 'Соки',
    'Редул' => 'Энергетики',
    'Мат энерги' => 'Энергетики',
    'Мл. Сир.' => 'Молочное',
    'Бурён' => 'Молочное',
    'Сас с картошкой' => 'Выпечка',
    'Сас. Сир.' => 'Выпечка',
    'Халапури' => 'Выпечка',
    'Патахар' => 'Выпечка',
    'Торт' => 'Выпечка',
    'Фатир' => 'Выпечка',
    'Шаурма Наиз' => 'Шаурма и гриль',
    'Шаурма В. ките' => 'Шаурма и гриль',
    'Грил' => 'Шаурма и гриль',
    'Крипсики' => 'Снеки',
    'Крипсики ос.' => 'Снеки',
    'Пемол 1 л' => 'Бытовая химия',
    'Фейри 1 л' => 'Бытовая химия',
    'Мицеллка' => 'Косметика',
    'Мама' => 'Бакалея',
    'Стакан' => 'Посуда',
];

$products = Database::fetchAll('SELECT id, name, category FROM products ORDER BY id');
$updated = 0;
$unknown = [];
foreach ($products as $product) {
    $name = (string) $product['name'];
    if (!isset($map[$name])) {
        $unknown[] = $name;
        continue;
    }
    if ((string) $product['category'] !== $map[$name]) {
        Database::query('UPDATE products SET category = ?, updated_at = ? WHERE id = ?', [$map[$name], now(), (int) $product['id']]);
        $updated++;
    }
}

echo 'Обновлено товаров: ' . $updated . PHP_EOL;
if ($unknown !== []) {
    echo 'Без сопоставления (' . count($unknown) . '):' . PHP_EOL;
    foreach ($unknown as $name) {
        echo '  - ' . $name . PHP_EOL;
    }
}
echo PHP_EOL . 'Категории:' . PHP_EOL;
foreach (Database::fetchAll('SELECT category, COUNT(*) AS n FROM products GROUP BY category ORDER BY category') as $row) {
    echo '  ' . $row['category'] . ': ' . $row['n'] . PHP_EOL;
}
