<?php

declare(strict_types=1);

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
     * @param int|null $a_user_id user id (default is current user)
     * @return bool true, if everything is ok
     */
    public function _checkAccess(string $a_cmd, string $a_permission, int $a_ref_id, int $a_obj_id, ?int $a_user_id = null): bool
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
     * Checks whether the Object is online
     * @param int $a_id
     * @return bool
     */
    public static function checkOnline(int $a_id): bool
    {
        global $ilDB;

        $object_id = $ilDB->quote($a_id, "integer");
        $select_sql = "SELECT is_online FROM " . ilOpencastEventPlugin::TABLE_NAME . " WHERE id = $object_id";

        $set = $ilDB->query($select_sql);

        $rec = $ilDB->fetchAssoc($set);
        return (bool) $rec["is_online"];
    }

    /**
     * Sets default RBAC permissions upon object creation
     *
     * @param int $ref_id ref id
     */
    public static function setDefaultPerms(int $ref_id): void
    {
        global $DIC;
        $parent_id = $DIC->repositoryTree()->getParentId($ref_id);
        $parent_obj = ilObjectFactory::getInstanceByRefId($parent_id);
        if (!$parent_obj) {
            return;
        }
        self::setDefaultMemberPerms($ref_id, $parent_obj);
        self::setDefaultTutorPerms($ref_id, $parent_obj);
        self::setDefaultAdminPerms($ref_id, $parent_obj);
    }

    /**
     * Sets default RBAC permissions for members
     *
     * @param int $ref_id ref id
     * @param ilObjCourse $parent_obj the parent object
     */
    private static function setDefaultMemberPerms(int $ref_id, ilObjCourse $parent_obj): void
    {
        global $DIC;
        $member_role_id = $parent_obj->getDefaultMemberRole();
        $member_roles = ['visible', 'read'];
        $ops_ids = [];
        foreach ($member_roles as $role_name) {
            $ops_ids[] = $DIC->rbac()->review()->_getOperationIdByName($role_name);
        }
        $DIC->rbac()->admin()->grantPermission($member_role_id, $ops_ids, $ref_id);
    }

    /**
     * Sets default RBAC permissions for tutors
     *
     * @param int $ref_id ref id
     * @param ilObjCourse $parent_obj the parent object
     */
    private static function setDefaultTutorPerms(int $ref_id, ilObjCourse $parent_obj): void
    {
        global $DIC;
        $tutor_role_id = $parent_obj->getDefaultTutorRole();
        $tutor_roles = ['visible', 'read', 'copy'];
        $ops_ids = [];
        foreach ($tutor_roles as $role_name) {
            $ops_ids[] = $DIC->rbac()->review()->_getOperationIdByName($role_name);
        }
        $DIC->rbac()->admin()->grantPermission($tutor_role_id, $ops_ids, $ref_id);
    }

    /**
     * Sets default RBAC permissions for admins
     *
     * @param int $ref_id ref id
     * @param ilObjCourse $parent_obj the parent object
     */
    private static function setDefaultAdminPerms(int $ref_id, ilObjCourse $parent_obj): void
    {
        global $DIC;
        $admin_role_id = $parent_obj->getDefaultAdminRole();
        $admin_roles = ['visible', 'read', 'copy', 'write', 'delete'];
        $ops_ids = [];
        foreach ($admin_roles as $role_name) {
            $ops_ids[] = $DIC->rbac()->review()->_getOperationIdByName($role_name);
        }
        $DIC->rbac()->admin()->grantPermission($admin_role_id, $ops_ids, $ref_id);
    }
}
