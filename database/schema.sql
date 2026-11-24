-- PIZ — схема базы данных (MySQL 5.7+ / MariaDB 10.3+)
-- Все денежные значения хранятся в копейках (целое число), чтобы избежать ошибок округления.

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  login VARCHAR(64) NOT NULL,
  name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'cashier',
  permissions TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_login (login)
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
  ('currency', '₽'),
  ('receipt_header', ''),
  ('receipt_footer', 'Спасибо за покупку! Ждём вас снова.'),
  ('low_stock_default', '3'),
  ('allow_negative_stock', '1'),
  ('timezone', 'Europe/Moscow');
