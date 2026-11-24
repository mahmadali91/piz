<?php
declare(strict_types=1);

namespace PIZ\Controllers;

use PIZ\Audit;
use PIZ\Auth;
use PIZ\Models\Inventory;
use PIZ\Services\PdfService;
use PIZ\View;

final class InventoryController
{
    public function index(): void
    {
        Auth::requirePermission('inventory.manage');
        View::render('inventory/index', [
            'title' => 'Инвентаризация',
            'active' => 'inventory',
            'user' => Auth::user(),
            'sessions' => Inventory::all(100),
        ]);
    }

    public function create(): void
    {
        Auth::requirePermission('inventory.manage');
        $warehouse = trim((string) ($_POST['warehouse'] ?? 'Основной склад')) ?: 'Основной склад';
        $note = trim((string) ($_POST['note'] ?? ''));
        try {
            $id = Inventory::create($warehouse, $note, Auth::id());
            Audit::log('inventory.create', ['id' => $id, 'warehouse' => $warehouse]);
            flash('success', 'Инвентаризация создана. Внесите фактические остатки.');
            redirect('/inventory/' . $id);
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect('/inventory');
        }
    }

    public function show(string $id): void
    {
        Auth::requirePermission('inventory.manage');
        $session = Inventory::find((int) $id);
        if (!$session) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Инвентаризация не найдена'], 'layout');
            return;
        }
        View::render('inventory/show', [
            'title' => 'Инвентаризация ' . $session['number'],
            'active' => 'inventory',
            'user' => Auth::user(),
            'session' => $session,
            'lines' => Inventory::lines((int) $session['id']),
        ]);
    }

    public function save(string $id): void
    {
        Auth::requirePermission('inventory.manage');
        $actuals = [];
        foreach (($_POST['actual'] ?? []) as $productId => $value) {
            $actuals[(int) $productId] = (int) $value;
        }
        try {
            Inventory::saveLines((int) $id, $actuals);
            Audit::log('inventory.save', ['id' => (int) $id]);
            flash('success', 'Фактические остатки сохранены.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/inventory/' . (int) $id);
    }

    public function complete(string $id): void
    {
        Auth::requirePermission('inventory.manage');
        $actuals = [];
        foreach (($_POST['actual'] ?? []) as $productId => $value) {
            $actuals[(int) $productId] = (int) $value;
        }
        try {
            Inventory::saveLines((int) $id, $actuals);
            Inventory::complete((int) $id, Auth::id(), trim((string) ($_POST['note'] ?? '')));
            Audit::log('inventory.complete', ['id' => (int) $id]);
            flash('success', 'Инвентаризация завершена, остатки обновлены.');
            redirect('/inventory/' . (int) $id);
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect('/inventory/' . (int) $id);
        }
    }

    public function pdf(string $id): void
    {
        Auth::requirePermission('inventory.manage');
        $session = Inventory::find((int) $id);
        if (!$session) {
            http_response_code(404);
            return;
        }
        PdfService::inventoryAct($session, Inventory::lines((int) $session['id']));
    }
}
