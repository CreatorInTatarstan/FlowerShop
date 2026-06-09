<?php
/**
 * Класс для работы с базой данных через PDO
 * Использует Singleton-паттерн для единственного подключения
 */

if (!defined('FLOWER_SHOP')) {
    die('Прямой доступ запрещён');
}

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    /**
     * Приватный конструктор для Singleton
     */
    private function __construct()
    {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die('Ошибка подключения к БД: ' . $e->getMessage());
            } else {
                die('Ошибка подключения к базе данных. Обратитесь к администратору.');
            }
        }
    }

    /**
     * Получить экземпляр класса (Singleton)
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Получить объект PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Выполнить запрос с параметрами
     * 
     * @param string $sql SQL-запрос с плейсхолдерами
     * @param array $params Параметры для подстановки
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die('Ошибка SQL: ' . $e->getMessage() . '<br>SQL: ' . $sql);
            } else {
                die('Произошла ошибка при работе с базой данных.');
            }
        }
    }

    /**
     * Получить одну строку
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Получить все строки
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Получить одно значение
     */
    public function fetchValue(string $sql, array $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }

    /**
     * Получить ID последней вставленной записи
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Начать транзакцию
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Зафиксировать транзакцию
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Откатить транзакцию
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    // Запрет клонирования и десериализации
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Нельзя десериализовать Singleton");
    }
}

// Функция-помощник для быстрого получения подключения
function db(): Database
{
    return Database::getInstance();
}
