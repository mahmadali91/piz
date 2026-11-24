-- PIZ — схема базы данных (MySQL 5.7+ / MariaDB 10.3+)
-- Все денежные значения хранятся в копейках (целое число), чтобы избежать ошибок округления.

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  login VARCHAR(64) NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'cashier',
  permissions TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_login (login),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  ip VARCHAR(45) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_resets_token (token_hash),
  KEY idx_password_resets_user (user_id, created_at),
  KEY idx_password_resets_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku VARCHAR(32) NULL,
  name VARCHAR(160) NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'Без категории',
  purchase_price_kop INT UNSIGNED NOT NULL DEFAULT 0,
  sale_price_kop INT UNSIGNED NOT NULL DEFAULT 0,
  stock_qty INT NOT NULL DEFAULT 0,
  unit VARCHAR(16) NOT NULL DEFAULT 'шт',
  low_stock INT NOT NULL DEFAULT 3,
  photo VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_products_active (active),
  KEY idx_products_name (name),
  KEY idx_products_category (category),
  KEY idx_products_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number VARCHAR(32) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  total_kop INT UNSIGNED NOT NULL DEFAULT 0,
  cost_kop INT UNSIGNED NOT NULL DEFAULT 0,
  discount_kop INT UNSIGNED NOT NULL DEFAULT 0,
  payment_method VARCHAR(16) NOT NULL DEFAULT 'cash',
  received_kop INT UNSIGNED NULL,
  change_kop INT UNSIGNED NULL,
  customer VARCHAR(120) NULL,
  comment VARCHAR(255) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'completed',
  shift_id INT UNSIGNED NULL,
  client_token VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  canceled_at DATETIME NULL,
  canceled_by INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sales_number (number),
  UNIQUE KEY uq_sales_token (client_token),
  KEY idx_sales_created (created_at),
  KEY idx_sales_status (status),
  KEY idx_sales_payment (payment_method),
  KEY idx_sales_user (user_id),
  KEY idx_sales_shift (shift_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sale_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sale_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  price_kop INT UNSIGNED NOT NULL DEFAULT 0,
  cost_kop INT UNSIGNED NOT NULL DEFAULT 0,
  total_kop INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_items_sale (sale_id),
  KEY idx_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  type VARCHAR(16) NOT NULL,
  qty_delta INT NOT NULL,
  qty_after INT NOT NULL,
  unit_cost_kop INT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  sale_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_moves_product (product_id, created_at),
  KEY idx_moves_sale (sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS product_receipts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  qty INT NOT NULL,
  unit_cost_kop INT UNSIGNED NOT NULL DEFAULT 0,
  total_kop INT UNSIGNED NOT NULL DEFAULT 0,
  comment VARCHAR(255) NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_receipts_product (product_id, created_at),
  KEY idx_receipts_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_price_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  old_purchase_kop INT UNSIGNED NOT NULL DEFAULT 0,
  new_purchase_kop INT UNSIGNED NOT NULL DEFAULT 0,
  old_sale_kop INT UNSIGNED NOT NULL DEFAULT 0,
  new_sale_kop INT UNSIGNED NOT NULL DEFAULT 0,
  source VARCHAR(24) NOT NULL DEFAULT 'manual',
  comment VARCHAR(255) NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_price_history_product (product_id, created_at),
  KEY idx_price_history_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS writeoffs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  qty INT NOT NULL,
  unit_cost_kop INT UNSIGNED NOT NULL DEFAULT 0,
  total_kop INT UNSIGNED NOT NULL DEFAULT 0,
  employee_name VARCHAR(120) NULL,
  reason VARCHAR(80) NOT NULL DEFAULT 'Расход',
  comment VARCHAR(255) NULL,
  user_id INT UNSIGNED NULL,
  shift_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_writeoffs_created (created_at),
  KEY idx_writeoffs_product (product_id),
  KEY idx_writeoffs_shift (shift_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shifts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  opened_by INT UNSIGNED NOT NULL,
  opened_at DATETIME NOT NULL,
  closed_by INT UNSIGNED NULL,
  closed_at DATETIME NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'open',
  note VARCHAR(255) NULL,
  sales_count INT UNSIGNED NOT NULL DEFAULT 0,
  revenue_kop INT UNSIGNED NOT NULL DEFAULT 0,
  cost_kop INT UNSIGNED NOT NULL DEFAULT 0,
  expense_kop INT UNSIGNED NOT NULL DEFAULT 0,
  profit_kop INT NOT NULL DEFAULT 0,
  stock_units INT NOT NULL DEFAULT 0,
  stock_value_kop INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_shifts_status (status),
  KEY idx_shifts_opened (opened_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shift_stock (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  shift_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  sku VARCHAR(32) NULL,
  qty INT NOT NULL DEFAULT 0,
  unit_cost_kop INT UNSIGNED NOT NULL DEFAULT 0,
  sale_price_kop INT UNSIGNED NOT NULL DEFAULT 0,
  stock_value_kop INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_shift_stock_shift (shift_id),
  KEY idx_shift_stock_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS suppliers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  contact VARCHAR(120) NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(120) NULL,
  address VARCHAR(255) NULL,
  inn VARCHAR(20) NULL,
  note VARCHAR(500) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_suppliers_name (name),
  KEY idx_suppliers_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_invoices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_id INT UNSIGNED NOT NULL,
  number VARCHAR(64) NOT NULL,
  invoice_date DATE NOT NULL,
  due_date DATE NULL,
  total_kop INT UNSIGNED NOT NULL DEFAULT 0,
  paid_kop INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(16) NOT NULL DEFAULT 'open',
  comment VARCHAR(255) NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_supplier_invoice (supplier_id, number),
  KEY idx_invoices_supplier (supplier_id, invoice_date),
  KEY idx_invoices_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_invoice_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  qty INT NOT NULL,
  unit_cost_kop INT UNSIGNED NOT NULL DEFAULT 0,
  total_kop INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_invoice_items_invoice (invoice_id),
  KEY idx_invoice_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  supplier_id INT UNSIGNED NOT NULL,
  invoice_id INT UNSIGNED NOT NULL,
  amount_kop INT UNSIGNED NOT NULL DEFAULT 0,
  method VARCHAR(16) NOT NULL DEFAULT 'cash',
  comment VARCHAR(255) NULL,
  user_id INT UNSIGNED NULL,
  paid_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_supplier_payments_invoice (invoice_id, paid_at),
  KEY idx_supplier_payments_supplier (supplier_id, paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_sessions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number VARCHAR(32) NOT NULL,
  warehouse VARCHAR(120) NOT NULL DEFAULT 'Основной склад',
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  started_by INT UNSIGNED NOT NULL,
  started_at DATETIME NOT NULL,
  completed_by INT UNSIGNED NULL,
  completed_at DATETIME NULL,
  note VARCHAR(255) NULL,
  total_products INT NOT NULL DEFAULT 0,
  total_calculated INT NOT NULL DEFAULT 0,
  total_actual INT NOT NULL DEFAULT 0,
  total_diff INT NOT NULL DEFAULT 0,
  total_value_kop INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inventory_number (number),
  KEY idx_inventory_status (status, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_lines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  sku VARCHAR(32) NULL,
  unit VARCHAR(16) NOT NULL DEFAULT 'шт',
  calculated_qty INT NOT NULL DEFAULT 0,
  actual_qty INT NULL,
  diff_qty INT NOT NULL DEFAULT 0,
  cost_kop INT UNSIGNED NOT NULL DEFAULT 0,
  diff_value_kop INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_inventory_lines_session (session_id),
  KEY idx_inventory_lines_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  skey VARCHAR(64) NOT NULL,
  svalue TEXT NULL,
  PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  action VARCHAR(64) NOT NULL,
  details TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_audit_created (created_at),
  KEY idx_audit_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip VARCHAR(45) NOT NULL,
  login VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_attempts_ip (ip, created_at),
  KEY idx_attempts_login (login, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (skey, svalue) VALUES
  ('store_name', 'КИОСК ДОНЕР'),
  ('store_address', ''),
  ('store_phone', ''),
  ('site_url', 'https://piz.itop7.ru'),
  ('currency', '₽'),
  ('receipt_header', ''),
  ('receipt_footer', 'Спасибо за покупку! Ждём вас снова.'),
  ('low_stock_default', '3'),
  ('allow_negative_stock', '1'),
  ('timezone', 'Europe/Moscow');
