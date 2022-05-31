<?php

include_once("./Services/Repository/classes/class.ilRepositoryObjectPlugin.php");

/**
 * Class ilOpencastEventPlugin
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
class ilOpencastEventPlugin extends ilRepositoryObjectPlugin
{
    const ID = 'xoce';
    const NAME = 'OpencastEvent';
    const TABLE_NAME = 'rep_robj_' . self::ID . '_data';

    public function getPluginName()
    {
        return self::NAME;
    }

    protected function uninstallCustom()
    {
        global $DIC;
        $db = $DIC->database();
        $db->dropTable(self::TABLE_NAME, false);
    }

    /**
     * @inheritdoc
     */
    public function allowCopy()
    {
        return true;
    }
}
