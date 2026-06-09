-- =====================================================
-- База данных интернет-магазина цветов "Цветочная лавка"
-- Дипломная работа
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- Создание базы данных (раскомментируйте при необходимости)
-- CREATE DATABASE IF NOT EXISTS `flower_shop` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `flower_shop`;

-- =====================================================
-- Таблица пользователей
-- =====================================================
CREATE TABLE `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL COMMENT 'Имя пользователя',
  `email` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Email (логин)',
  `phone` VARCHAR(20) DEFAULT NULL COMMENT 'Телефон',
  `password_hash` VARCHAR(255) NOT NULL COMMENT 'Хеш пароля',
  `role` ENUM('admin','manager','client') NOT NULL DEFAULT 'client' COMMENT 'Роль пользователя',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Пользователи системы';

-- =====================================================
-- Таблица категорий
-- =====================================================
CREATE TABLE `categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL COMMENT 'Название категории',
  `slug` VARCHAR(100) NOT NULL UNIQUE COMMENT 'URL-имя',
  `description` TEXT DEFAULT NULL COMMENT 'Описание',
  `image` VARCHAR(255) DEFAULT NULL COMMENT 'Изображение',
  `parent_id` INT(11) DEFAULT NULL COMMENT 'Родительская категория',
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_parent` (`parent_id`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Категории товаров';

-- =====================================================
-- Таблица товаров
-- =====================================================
CREATE TABLE `products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_id` INT(11) NOT NULL,
  `name` VARCHAR(200) NOT NULL COMMENT 'Название товара',
  `description` TEXT DEFAULT NULL COMMENT 'Описание',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Цена',
  `stock_quantity` INT(11) NOT NULL DEFAULT 0 COMMENT 'Остаток на складе',
  `image` VARCHAR(255) DEFAULT NULL COMMENT 'Изображение',
  `composition` TEXT DEFAULT NULL COMMENT 'Состав букета',
  `size` VARCHAR(50) DEFAULT NULL COMMENT 'Размер',
  `is_available` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Доступен к заказу',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_available` (`is_available`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Товары магазина';

-- =====================================================
-- Таблица заказов
-- =====================================================
CREATE TABLE `orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL COMMENT 'ID пользователя (NULL для гостей)',
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Сумма заказа',
  `status` ENUM('new','processing','delivery','completed','cancelled') NOT NULL DEFAULT 'new' COMMENT 'Статус заказа',
  `delivery_address` VARCHAR(500) NOT NULL COMMENT 'Адрес доставки',
  `delivery_date` DATE NOT NULL COMMENT 'Дата доставки',
  `delivery_time` VARCHAR(50) DEFAULT NULL COMMENT 'Время доставки',
  `recipient_name` VARCHAR(100) NOT NULL COMMENT 'Имя получателя',
  `recipient_phone` VARCHAR(20) NOT NULL COMMENT 'Телефон получателя',
  `payment_method` ENUM('cash','card','online') NOT NULL DEFAULT 'cash' COMMENT 'Способ оплаты',
  `comment` TEXT DEFAULT NULL COMMENT 'Комментарий',
  `promocode_id` INT(11) DEFAULT NULL COMMENT 'Применённый промокод',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Заказы клиентов';

-- =====================================================
-- Таблица позиций заказа
-- =====================================================
CREATE TABLE `order_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `quantity` INT(11) NOT NULL DEFAULT 1,
  `price` DECIMAL(10,2) NOT NULL COMMENT 'Цена на момент заказа',
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Позиции заказов';

-- =====================================================
-- Таблица поставщиков
-- =====================================================
CREATE TABLE `suppliers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL COMMENT 'Название поставщика',
  `contact_person` VARCHAR(100) DEFAULT NULL COMMENT 'Контактное лицо',
  `phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Поставщики';

-- =====================================================
-- Таблица поставок
-- =====================================================
CREATE TABLE `deliveries` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `delivery_date` DATE NOT NULL,
  `cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Закупочная стоимость',
  PRIMARY KEY (`id`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `fk_deliveries_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_deliveries_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Поставки товаров';

-- =====================================================
-- Таблица отзывов
-- =====================================================
CREATE TABLE `reviews` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `rating` TINYINT(1) NOT NULL DEFAULT 5 COMMENT 'Оценка 1-5',
  `comment` TEXT DEFAULT NULL,
  `is_approved` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Одобрен модератором',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Отзывы о товарах';

-- =====================================================
-- Таблица промокодов
-- =====================================================
CREATE TABLE `promocodes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Код промокода',
  `discount_percent` INT(11) NOT NULL DEFAULT 0 COMMENT 'Процент скидки',
  `valid_until` DATE DEFAULT NULL COMMENT 'Действителен до',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Промокоды';

-- =====================================================
-- ТЕСТОВЫЕ ДАННЫЕ
-- =====================================================

-- Пользователи (пароль для всех: password123)
-- Хеш получен через password_hash('password123', PASSWORD_DEFAULT)
INSERT INTO `users` (`name`, `email`, `phone`, `password_hash`, `role`) VALUES
('Администратор', 'admin@flowers.local', '+7 (900) 000-00-00', '$2y$10$Hl3z7EjhOA5dsXbJgD3gDeCqZZrd7SIa6t1mZ.LZF6ck7u8fA1OxO', 'admin'),
('Менеджер Анна', 'manager@flowers.local', '+7 (900) 111-11-11', '$2y$10$Hl3z7EjhOA5dsXbJgD3gDeCqZZrd7SIa6t1mZ.LZF6ck7u8fA1OxO', 'manager'),
('Иван Петров', 'ivan@example.com', '+7 (900) 222-22-22', '$2y$10$Hl3z7EjhOA5dsXbJgD3gDeCqZZrd7SIa6t1mZ.LZF6ck7u8fA1OxO', 'client'),
('Мария Сидорова', 'maria@example.com', '+7 (900) 333-33-33', '$2y$10$Hl3z7EjhOA5dsXbJgD3gDeCqZZrd7SIa6t1mZ.LZF6ck7u8fA1OxO', 'client'),
('Елена Кузнецова', 'elena@example.com', '+7 (900) 444-44-44', '$2y$10$Hl3z7EjhOA5dsXbJgD3gDeCqZZrd7SIa6t1mZ.LZF6ck7u8fA1OxO', 'client');

-- Категории
INSERT INTO `categories` (`name`, `slug`, `description`, `image`, `parent_id`) VALUES
('Букеты', 'bouquets', 'Авторские и классические букеты на любой случай', 'cat_bouquets.jpg', NULL),
('Розы', 'roses', 'Розы всех сортов и оттенков', 'cat_roses.jpg', NULL),
('Тюльпаны', 'tulips', 'Свежие тюльпаны от лучших поставщиков', 'cat_tulips.jpg', NULL),
('Композиции', 'compositions', 'Цветочные композиции в коробках и корзинах', 'cat_compositions.jpg', NULL),
('Свадебные', 'wedding', 'Букеты невесты и свадебные композиции', 'cat_wedding.jpg', 1),
('На день рождения', 'birthday', 'Праздничные букеты', 'cat_birthday.jpg', 1);

-- Товары (15 шт.)
INSERT INTO `products` (`category_id`, `name`, `description`, `price`, `stock_quantity`, `image`, `composition`, `size`, `is_available`) VALUES
(2, 'Букет "Алые паруса"', 'Классический букет из 25 красных роз. Идеальный подарок для любимой.', 4500.00, 20, 'roses_red_25.jpg', '25 красных роз сорта "Гран-при", упаковка крафт, лента атласная', 'Высота 60 см', 1),
(2, 'Букет "Нежность"', 'Букет из 15 розовых роз с зеленью.', 2800.00, 15, 'roses_pink_15.jpg', '15 розовых роз, эвкалипт, упаковка', 'Высота 50 см', 1),
(2, 'Белые розы "Чистота"', 'Элегантный букет из 11 белых роз.', 2200.00, 25, 'roses_white_11.jpg', '11 белых роз, гипсофила, упаковка', 'Высота 55 см', 1),
(3, 'Тюльпаны микс 25 шт.', 'Яркий весенний букет из разноцветных тюльпанов.', 1800.00, 40, 'tulips_mix_25.jpg', '25 тюльпанов разных цветов, упаковка', 'Высота 40 см', 1),
(3, 'Розовые тюльпаны 51 шт.', 'Роскошный букет из 51 розового тюльпана.', 3900.00, 10, 'tulips_pink_51.jpg', '51 розовый тюльпан, лента', 'Высота 45 см', 1),
(1, 'Букет "Весеннее настроение"', 'Сборный букет из тюльпанов, ирисов и фрезий.', 2500.00, 12, 'spring_mood.jpg', 'Тюльпаны, ирисы, фрезии, эвкалипт', 'Высота 50 см', 1),
(1, 'Букет "Полевой ветер"', 'Букет в полевом стиле с ромашками и васильками.', 1900.00, 18, 'field_wind.jpg', 'Ромашки, васильки, лаванда, злаки', 'Высота 45 см', 1),
(4, 'Композиция "Сладкий сон"', 'Цветочная композиция в шляпной коробке.', 3200.00, 8, 'sweet_dream.jpg', 'Розы пионовидные, гортензия, эвкалипт, шляпная коробка', 'Диаметр 20 см', 1),
(4, 'Композиция "Праздник"', 'Праздничная композиция в корзине.', 4100.00, 6, 'celebration.jpg', 'Розы, хризантемы, лилии, корзина плетёная', 'Диаметр 30 см', 1),
(5, 'Букет невесты "Романтика"', 'Классический букет невесты из белых роз и пионов.', 5500.00, 5, 'bride_romance.jpg', 'Белые розы, пионы, эвкалипт, атласная лента', 'Диаметр 25 см', 1),
(5, 'Букет невесты "Нежный"', 'Воздушный букет в пастельных тонах.', 4800.00, 4, 'bride_tender.jpg', 'Розы кустовые, фрезии, гипсофила', 'Диаметр 22 см', 1),
(6, 'Букет "С Днём рождения!"', 'Яркий праздничный букет с воздушным шариком.', 2700.00, 14, 'birthday_bright.jpg', 'Герберы, розы, хризантемы, шарик', 'Высота 55 см', 1),
(2, 'Букет "101 роза"', 'Грандиозный букет из 101 розы.', 12500.00, 3, 'roses_101.jpg', '101 роза красная, упаковка премиум, лента', 'Высота 70 см', 1),
(3, 'Жёлтые тюльпаны 35 шт.', 'Солнечный букет из жёлтых тюльпанов.', 2400.00, 22, 'tulips_yellow_35.jpg', '35 жёлтых тюльпанов, упаковка', 'Высота 42 см', 1),
(1, 'Букет "Лавандовое поле"', 'Ароматный букет с лавандой.', 2100.00, 16, 'lavender_field.jpg', 'Лаванда, белые розы, эвкалипт', 'Высота 48 см', 1);

-- Поставщики
INSERT INTO `suppliers` (`name`, `contact_person`, `phone`, `email`, `address`) VALUES
('ООО "Цветочный мир"', 'Сергей Иванов', '+7 (495) 100-10-10', 'orders@flowerworld.ru', 'г. Москва, ул. Тепличная, 5'),
('Голландия Флора', 'Анна Петрова', '+7 (495) 200-20-20', 'info@hollandflora.ru', 'г. Москва, ул. Ботаническая, 12'),
('Эквадор Розы', 'Михаил Сидоров', '+7 (495) 300-30-30', 'sales@ecuador-roses.ru', 'г. Москва, Складская ул., 3');

-- Поставки
INSERT INTO `deliveries` (`supplier_id`, `product_id`, `quantity`, `delivery_date`, `cost`) VALUES
(3, 1, 50, '2025-04-15', 80000.00),
(2, 4, 100, '2025-04-20', 50000.00),
(1, 6, 30, '2025-04-22', 30000.00),
(2, 5, 50, '2025-04-25', 60000.00),
(3, 3, 40, '2025-04-28', 35000.00);

-- Заказы (5 шт.)
INSERT INTO `orders` (`user_id`, `total_amount`, `status`, `delivery_address`, `delivery_date`, `delivery_time`, `recipient_name`, `recipient_phone`, `payment_method`, `comment`, `created_at`) VALUES
(3, 4500.00, 'completed', 'г. Москва, ул. Ленина, д. 10, кв. 5', '2025-04-20', '10:00-12:00', 'Анна Петрова', '+7 (900) 555-55-55', 'card', 'Подарок жене на годовщину', '2025-04-19 14:30:00'),
(4, 2800.00, 'delivery', 'г. Москва, ул. Пушкина, д. 25, кв. 12', '2025-04-25', '14:00-16:00', 'Мария Сидорова', '+7 (900) 333-33-33', 'online', '', '2025-04-24 09:15:00'),
(5, 6300.00, 'processing', 'г. Москва, пр-т Мира, д. 100', '2025-04-26', '12:00-14:00', 'Елена Кузнецова', '+7 (900) 444-44-44', 'cash', 'Открытка с поздравлением', '2025-04-25 16:45:00'),
(3, 1800.00, 'new', 'г. Москва, ул. Гагарина, д. 7', '2025-04-27', '16:00-18:00', 'Иван Петров', '+7 (900) 222-22-22', 'card', '', '2025-04-26 11:20:00'),
(NULL, 5500.00, 'completed', 'г. Москва, ул. Тверская, д. 15', '2025-04-15', '11:00-13:00', 'Ольга Иванова', '+7 (900) 666-66-66', 'card', 'Свадебный букет', '2025-04-14 10:00:00');

-- Позиции заказов
INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 1, 1, 4500.00),
(2, 2, 1, 2800.00),
(3, 1, 1, 4500.00),
(3, 4, 1, 1800.00),
(4, 4, 1, 1800.00),
(5, 10, 1, 5500.00);

-- Промокоды
INSERT INTO `promocodes` (`code`, `discount_percent`, `valid_until`, `is_active`) VALUES
('WELCOME10', 10, '2025-12-31', 1),
('SPRING20', 20, '2025-06-30', 1),
('VIP15', 15, '2025-12-31', 1);

-- Отзывы
INSERT INTO `reviews` (`user_id`, `product_id`, `rating`, `comment`, `is_approved`) VALUES
(3, 1, 5, 'Прекрасный букет! Жена была в восторге. Свежие розы, красивая упаковка.', 1),
(4, 2, 5, 'Очень нежный букет, доставили вовремя. Спасибо!', 1),
(5, 4, 4, 'Хорошие тюльпаны, но хотелось бы больше разнообразия цветов.', 1),
(3, 6, 5, 'Букет произвёл впечатление! Рекомендую.', 1);
