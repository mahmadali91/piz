<?php
declare(strict_types=1);

namespace PIZ;

final class Permissions
{
    /** @var array<string, array<string, string>> */
    public const GROUPS = [
        'Рабочее место' => [
            'pos.use' => 'Оформление продаж (касса)',
        ],
        'Товары и склад' => [
            'products.view' => 'Просмотр товаров и остатков',
            'products.manage' => 'Добавление и редактирование товаров',
            'stock.manage' => 'Приход и инвентаризация',
            'finance.view' => 'Закупочные цены и прибыль',
        ],
        'Продажи' => [
            'sales.view_all' => 'Все продажи (а не только свои)',
            'sales.cancel' => 'Отмена (возврат) продаж',
        ],
        'Аналитика' => [
            'dashboard.view' => 'Дашборд',
            'reports.view' => 'Отчёты и экспорт',
        ],
        'Администрирование' => [
            'users.manage' => 'Сотрудники и права',
            'settings.manage' => 'Настройки киоска',
            'activity.view' => 'Журнал действий',
        ],
    ];

    /** @var array<string, string[]> */
    public const ROLES = [
        'admin' => [
            'dashboard.view', 'pos.use', 'products.view', 'products.manage', 'stock.manage',
            'finance.view', 'sales.view_all', 'sales.cancel', 'reports.view',
            'users.manage', 'settings.manage', 'activity.view',
        ],
        'manager' => [
            'dashboard.view', 'pos.use', 'products.view', 'products.manage', 'stock.manage',
            'finance.view', 'sales.view_all', 'sales.cancel', 'reports.view',
        ],
        'cashier' => [
            'pos.use', 'products.view',
        ],
    ];

    /** @return string[] */
    public static function all(): array
    {
        $all = [];
        foreach (self::GROUPS as $items) {
            $all = array_merge($all, array_keys($items));
        }
        return $all;
    }

    public static function label(string $permission): string
    {
        foreach (self::GROUPS as $items) {
            if (isset($items[$permission])) {
                return $items[$permission];
            }
        }
        return $permission;
    }

    /** @return string[] */
    public static function forRole(string $role): array
    {
        return self::ROLES[$role] ?? [];
    }

    /** @param string[] $permissions */
    public static function sanitize(array $permissions): array
    {
        $allowed = self::all();
        $permissions = array_values(array_unique(array_filter($permissions, static fn ($p) => in_array($p, $allowed, true))));
        return $permissions;
    }
}
