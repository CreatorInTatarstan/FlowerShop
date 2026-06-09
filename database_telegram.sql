-- =====================================================
-- Миграция: Telegram-интеграция
-- Применять ПОСЛЕ database.sql
-- =====================================================

-- Добавляем Telegram-поля в таблицу пользователей
ALTER TABLE `users`
    ADD COLUMN `telegram_id` BIGINT DEFAULT NULL COMMENT 'Telegram chat_id пользователя' AFTER `phone`,
    ADD COLUMN `telegram_username` VARCHAR(64) DEFAULT NULL COMMENT '@username в Telegram' AFTER `telegram_id`,
    ADD COLUMN `tg_link_token` VARCHAR(64) DEFAULT NULL COMMENT 'Одноразовый токен привязки' AFTER `telegram_username`,
    ADD COLUMN `tg_notifications` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Получать уведомления' AFTER `tg_link_token`,
    ADD UNIQUE INDEX `idx_telegram_id` (`telegram_id`),
    ADD INDEX `idx_tg_link_token` (`tg_link_token`);

-- =====================================================
-- Таблица состояний пользователей в боте
-- =====================================================
CREATE TABLE IF NOT EXISTS `telegram_states` (
    `telegram_id` BIGINT NOT NULL PRIMARY KEY,
    `state` VARCHAR(50) DEFAULT NULL COMMENT 'Текущее состояние (например, awaiting_address)',
    `data` TEXT DEFAULT NULL COMMENT 'JSON с накопленными данными',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Состояния FSM-диалогов в Telegram-боте';

-- =====================================================
-- Лог уведомлений Telegram (для отладки и истории)
-- =====================================================
CREATE TABLE IF NOT EXISTS `telegram_notifications_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `telegram_id` BIGINT NOT NULL,
    `event_type` VARCHAR(50) NOT NULL COMMENT 'order_created, status_changed, etc.',
    `message` TEXT NOT NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 1,
    `error` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tg_id` (`telegram_id`),
    KEY `idx_event` (`event_type`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Журнал отправленных Telegram-уведомлений';

-- =====================================================
-- Таблица настроек бота
-- =====================================================
CREATE TABLE IF NOT EXISTS `telegram_settings` (
    `setting_key` VARCHAR(50) NOT NULL PRIMARY KEY,
    `setting_value` TEXT DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Настройки Telegram-бота';

-- Базовые настройки
INSERT INTO `telegram_settings` (`setting_key`, `setting_value`, `description`) VALUES
('bot_token', '', 'Токен бота от @BotFather'),
('bot_username', '', 'Username бота без @'),
('webhook_url', '', 'URL webhook вашего сайта'),
('admin_chat_ids', '', 'ID администраторов через запятую'),
('notify_admins_on_order', '1', 'Уведомлять админов о новых заказах'),
('notify_admins_on_review', '1', 'Уведомлять админов о новых отзывах');
