<?php

include_once("./Services/Repository/classes/class.ilObjectPluginAccess.php");
require_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/OpencastEvent/classes/class.ilObjOpencastEvent.php");

/**
 * Class ilObjOpencastEventAccess
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
class ilObjOpencastEventAccess extends ilObjectPluginAccess
{

    /**
     * Checks whether a user may invoke a command or not
     * (this method is called by ilAccessHandler::checkAccess)
     *
     * Please do not check any preconditions handled by
     * ilConditionHandler here. Also don't do usual RBAC checks.
     *
     * @param string $a_cmd command (not permission!)
     * @param string $a_permission permission
     * @param int $a_ref_id reference id
     * @param int $a_obj_id object id
     * @param int $a_user_id user id (default is current user)
     * @return bool true, if everything is ok
     */
    public function _checkAccess($a_cmd, $a_permission, $a_ref_id, $a_obj_id, $a_user_id = 0)
    {
        global $ilUser, $ilAccess;

        if ($a_user_id == 0) {
            $a_user_id = $ilUser->getId();
        }

        switch ($a_permission) {
            case "read":
                if (!ilObjOpencastEventAccess::checkOnline($a_obj_id) &&
                    !$ilAccess->checkAccessOfUser($a_user_id, "write", "", $a_ref_id)) {
                    return false;
                }
                break;
            case "write":
                return $ilAccess->checkAccessOfUser($a_user_id, "write", "", $a_ref_id);
                break;
        }

        return true;
    }

    /**
     * @param $a_id int
     * @return bool
     */
    public static function checkOnline($a_id)
    {
        global $ilDB;

        $object_id = $ilDB->quote($a_id, "integer");
        $select_sql = "SELECT is_online FROM " . ilOpencastEventPlugin::TABLE_NAME . " WHERE id = $object_id";

        $set = $ilDB->query($select_sql);

        $rec = $ilDB->fetchAssoc($set);
        return (boolean) $rec["is_online"];
    }
}
