<?php
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

    /**
     * @return string
     */
    public static function returnDbTableName()
    {
        return self::TABLE_NAME;
    }

    /**
     * @return string
     */
    public function getConnectorContainerName()
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

    /**
     * @param string $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param $name
     * @return mixed
     */
    public static function getConfig($name)
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
     * @param $name
     * @param $value
     */
    public static function setConfig($name, $value): void
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
