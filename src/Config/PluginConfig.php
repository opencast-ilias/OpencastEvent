<?php

declare(strict_types=1);
namespace elanev\OpencastEvent\Config;

use ActiveRecord;

class PluginConfig extends ActiveRecord
{
    public const TABLE_NAME = 'rep_robj_xoce_config';
    public const F_THUMBNAIL_LINK = 'thumbnail_link';

    /**
     * @var array
     */
    protected static $cached_config = [];

    public static function returnDbTableName(): string
    {
        return self::TABLE_NAME;
    }

    public function getConnectorContainerName(): string
    {
        return self::TABLE_NAME;
    }

    /**
     * @var string
     * @db_has_field        true
     * @db_is_unique        true
     * @db_is_primary       true
     * @db_is_notnull       true
     * @db_fieldtype        text
     * @db_length           250
     */
    protected $name;

    /**
     * @var string
     * @db_has_field        true
     * @db_fieldtype        text
     * @db_length           4000
     */
    protected $value;

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public static function getConfig(string $name): mixed
    {
        if (!isset(self::$cached_config[$name])) {
            if (self::where(['name' => $name])->hasSets()) {
                $obj = new self($name);
                try {
                    self::$cached_config[$name] = json_decode($obj->getValue());
                } catch (\Exception $e) {
                    return null;
                }
            } else {
                return null;
            }
        }

        return self::$cached_config[$name];
    }

    /**
     * @param string $name
     */
    public static function setConfig(?string $name, mixed $value): void
    {
        if (self::where(['name' => $name])->hasSets()) {
            $obj = new self($name);
            $obj->setValue(json_encode($value));
            $obj->update();
        } else {
            $obj = new self;
            $obj->setName($name);
            $obj->setValue(json_encode($value));
            $obj->create();
        }
    }
}
