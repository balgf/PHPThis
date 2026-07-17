<?php

declare(strict_types=1);

namespace PHPThis\Database;

use InvalidArgumentException;
use PDO;
use PDOStatement;
use UnexpectedValueException;

final readonly class Connection
{
    private function __construct(
        private PDO $pdo,
        private QueryBudget $queryBudget,
        private QueryTrace $queryTrace,
    ) {
    }

    /** @param array<int, mixed> $options */
    public static function connect(
        string $dsn,
        QueryBudget $queryBudget,
        QueryTrace $queryTrace,
        ?string $username = null,
        ?string $password = null,
        array $options = [],
    ): self {
        $defaults = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new self(
            new PDO($dsn, $username, $password, $defaults + $options),
            $queryBudget,
            $queryTrace,
        );
    }

    /**
     * @param array<string, bool|int|string|null> $parameters
     * @return list<array<string, mixed>>
     */
    public function selectAllRows(string $sql, array $parameters = []): array
    {
        $rows = [];

        foreach ($this->run($sql, $parameters)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = $this->associativeRow($row);
        }

        return $rows;
    }

    /**
     * @param array<string, bool|int|string|null> $parameters
     * @return array<string, mixed>|null
     */
    public function selectOneRow(string $sql, array $parameters = []): ?array
    {
        $row = $this->run($sql, $parameters)->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->associativeRow($row);
    }

    /** @param array<string, bool|int|string|null> $parameters */
    public function executeStatement(string $sql, array $parameters = []): int
    {
        return $this->run($sql, $parameters)->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * @param array<string, bool|int|string|null> $parameters
     */
    private function run(string $sql, array $parameters): PDOStatement
    {
        $this->queryBudget->recordStatement();
        $startedAt = (float) hrtime(true);
        $failed = true;

        try {
            $statement = $this->pdo->prepare($sql);

            foreach ($parameters as $name => $value) {
                $parameterName = $this->parameterName($name);
                $placeholder = $parameterName[0] === ':' ? $parameterName : ':' . $parameterName;
                $statement->bindValue($placeholder, $value, $this->parameterType($value));
            }

            $statement->execute();
            $failed = false;
            return $statement;
        } finally {
            $durationUs = (int) round(((float) hrtime(true) - $startedAt) / 1_000);
            $this->queryTrace->recordStatement($sql, $durationUs, $failed);
        }
    }

    /** @return array<string, mixed> */
    private function associativeRow(mixed $row): array
    {
        if (!is_array($row)) {
            throw new UnexpectedValueException('PDO returned a non-array row.');
        }

        $associativeRow = [];

        foreach ($row as $column => $value) {
            if (!is_string($column)) {
                throw new UnexpectedValueException('PDO returned a non-string column name.');
            }

            $associativeRow[$column] = $value;
        }

        return $associativeRow;
    }

    private function parameterName(mixed $name): string
    {
        if (!is_string($name) || $name === '') {
            throw new InvalidArgumentException('SQL parameter names must be non-empty strings.');
        }

        return $name;
    }

    private function parameterType(bool|int|string|null $value): int
    {
        return match (true) {
            is_bool($value) => PDO::PARAM_BOOL,
            is_int($value) => PDO::PARAM_INT,
            is_null($value) => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }
}
