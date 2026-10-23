-- TestPrep MySQL schema (phpMyAdmin में Import करें)
CREATE TABLE IF NOT EXISTS tp_kv (
  k VARCHAR(64) NOT NULL PRIMARY KEY,
  v LONGTEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- settings, invoices, ledger, codes, rewards, queue, logs, orders, admin_otp — सब इसी तालिका में JSON के रूप में रखे जाते हैं।

-- ================= छात्र रजिस्ट्रेशन (v5) =================
CREATE TABLE IF NOT EXISTS tp_users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_name  VARCHAR(100) NOT NULL,
  father_name   VARCHAR(100) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  mobile        CHAR(10)     NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tp_users_email  (email),
  UNIQUE KEY uq_tp_users_mobile (mobile),
  KEY idx_tp_users_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
