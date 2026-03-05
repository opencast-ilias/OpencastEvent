<?php

declare(strict_types=1);
require_once __DIR__ . "/../vendor/autoload.php";

/**
 * Class ilOpencastEventPlugin
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
class ilOpencastEventPlugin extends ilRepositoryObjectPlugin
{
    public const ID = 'xoce';
    public const PLUGIN_NAME = 'OpencastEvent';
    public const TABLE_NAME = 'rep_robj_' . self::ID . '_data';

    /**
     * @inheritdoc
     */
    public function getPluginName(): string
    {
        return self::PLUGIN_NAME;
    }

    /**
     * @inheritdoc
     */
    protected function uninstallCustom(): void
    {
        global $DIC;
        $db = $DIC->database();
        $db->dropTable(self::TABLE_NAME, false);
    }

    /**
     * @inheritdoc
     */
    public function allowCopy(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function _getIcon(string $a_type): string
    {
        return './Customizing/global/plugins/Services/Repository/RepositoryObject/OpencastEvent/templates/images/icon_xoce.svg';
    }


    /**
     * Provide a readable path for loading the resources like js and css files by ILIAS.
     * @return string
     */
    public function getResourcesPath(): string
    {
        return './Customizing/global/plugins/Services/Repository/RepositoryObject/' . self::PLUGIN_NAME;
    }
}
