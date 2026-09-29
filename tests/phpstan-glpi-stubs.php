<?php

class CommonITILActor
{
    public const ASSIGN = 1;
}

class Ticket
{
    /** @var array<string, mixed> */
    public $input = [];

    /** @var array<string, mixed> */
    public $fields = [];

    public function isNewItem(): bool
    {
        return false;
    }

    public function loadActors(): void {}

    /** @return array<int, array{id: int, groups_id: int}> */
    public function getGroups(int $type): array
    {
        return [];
    }

    /** @return array<int, array{id: int, users_id: int}> */
    public function getUsers(int $type): array
    {
        return [];
    }
}

class Config
{
    /** @return array<string, string> */
    public static function getConfigurationValues(string $context, array $names = []): array
    {
        return [];
    }

    public static function setConfigurationValues(string $context, array $values): void {}

    public static function deleteConfigurationValues(string $context, array $names): void {}
}

class Plugin
{
    public static function isPluginActive(string $name): bool
    {
        return false;
    }

    /** @return mixed */
    public static function getInfo(string $name, string $key = null)
    {
        return null;
    }
}

class PluginBehaviorsConfig
{
    public static function getInstance(): self
    {
        return new self();
    }

    /** @return mixed */
    public function getField(string $name)
    {
        return null;
    }
}

class Toolbox
{
    public static function logInFile(string $name, string $line, bool $append): void {}
}
