-- Laravel database queue infrastructure (absent from the old public schema).
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `queue` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL,
  `reserved_at` INT UNSIGNED NULL,
  `available_at` INT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  INDEX `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` VARCHAR(255) NOT NULL PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `total_jobs` INT NOT NULL,
  `pending_jobs` INT NOT NULL,
  `failed_jobs` INT NOT NULL,
  `failed_job_ids` LONGTEXT NOT NULL,
  `options` MEDIUMTEXT NULL,
  `cancelled_at` INT NULL,
  `created_at` INT NOT NULL,
  `finished_at` INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `uuid` VARCHAR(255) NOT NULL UNIQUE,
  `connection` TEXT NOT NULL,
  `queue` TEXT NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
