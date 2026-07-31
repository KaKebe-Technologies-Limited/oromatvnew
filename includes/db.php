<?php
require_once __DIR__ . '/../config.php';

/**
 * Thin mysqli wrapper exposing the same prepare()/execute()/fetch()/fetchAll()
 * shape the rest of the app already calls, so callers don't need to change.
 */
final class DbStatement
{
    private mysqli_stmt $stmt;
    private mysqli_result|false|null $result = null;

    public function __construct(mysqli_stmt $stmt)
    {
        $this->stmt = $stmt;
    }

    public function execute(array $params = []): bool
    {
        if ($params) {
            $types = '';
            foreach ($params as $p) {
                if (is_int($p)) {
                    $types .= 'i';
                } elseif (is_float($p)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            $this->stmt->bind_param($types, ...$params);
        }

        $ok = $this->stmt->execute();
        $this->result = $this->stmt->get_result();
        return $ok;
    }

    /** @return array<string,mixed>|false */
    public function fetch(): array|false
    {
        if (!$this->result) {
            return false;
        }
        return $this->result->fetch_assoc() ?? false;
    }

    /** @return array<int,array<string,mixed>> */
    public function fetchAll(): array
    {
        if (!$this->result) {
            return [];
        }
        return $this->result->fetch_all(MYSQLI_ASSOC);
    }
}

final class Db
{
    public mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function prepare(string $sql): DbStatement
    {
        $stmt = $this->mysqli->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('DB prepare failed: ' . $this->mysqli->error);
        }
        return new DbStatement($stmt);
    }

    public function query(string $sql): DbStatement
    {
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    public function lastInsertId(): string
    {
        return (string) $this->mysqli->insert_id;
    }
}

function db(): Db
{
    static $db = null;
    if ($db === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $mysqli->set_charset('utf8mb4');
        $db = new Db($mysqli);
    }
    return $db;
}
