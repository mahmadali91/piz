<?php
declare(strict_types=1);

// Service worker отдаётся через PHP, чтобы гарантировать no-cache:
// статические .js на Beget кешируются nginx на 7 дней, и обновления SW задерживаются.
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

readfile(__DIR__ . '/sw.js');
