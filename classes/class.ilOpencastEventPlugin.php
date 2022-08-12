<?php

include_once("./Services/Repository/classes/class.ilRepositoryObjectPlugin.php");

/**
 * Class ilOpencastEventPlugin
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
class ilOpencastEventPlugin extends ilRepositoryObjectPlugin
{
    public const ID = 'xoce';
    public const NAME = 'OpencastEvent';
    public const TABLE_NAME = 'rep_robj_' . self::ID . '_data';

    public function getPluginName(): string
    {
        return self::NAME;
    }

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
}
