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
     * Default RBAC permissions granted per parent-course role on creation.
     *
     * @var array<string, list<string>>
     */
    private const DEFAULT_PERMISSIONS = [
        'member' => ['visible', 'read'],
        'tutor' => ['visible', 'read', 'copy'],
        'admin' => ['visible', 'read', 'copy', 'write', 'delete'],
    ];

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
        global $DIC;

        if (!$a_user_id) {
            $a_user_id = $DIC->user()->getId();
        }

        $access = $DIC->access();

        switch ($a_permission) {
            case "read":
                if (!self::checkOnline($a_obj_id) &&
                    !$access->checkAccessOfUser($a_user_id, "write", "", $a_ref_id)) {
                    return false;
                }
                break;
            case "write":
                return $access->checkAccessOfUser($a_user_id, "write", "", $a_ref_id);
        }

        return true;
    }

    /**
     * Checks whether the Object is online
     */
    public static function checkOnline(int $a_id): bool
    {
        global $DIC;

        $db = $DIC->database();
        $set = $db->queryF(
            'SELECT is_online FROM ' . ilOpencastEventPlugin::TABLE_NAME . ' WHERE id = %s',
            ['integer'],
            [$a_id]
        );

        $rec = $db->fetchAssoc($set);
        return (bool) ($rec["is_online"] ?? false);
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
        if (!$parent_obj instanceof ilObjCourse) {
            return;
        }

        self::grantPermissions($ref_id, $parent_obj->getDefaultMemberRole(), self::DEFAULT_PERMISSIONS['member']);
        self::grantPermissions($ref_id, $parent_obj->getDefaultTutorRole(), self::DEFAULT_PERMISSIONS['tutor']);
        self::grantPermissions($ref_id, $parent_obj->getDefaultAdminRole(), self::DEFAULT_PERMISSIONS['admin']);
    }

    /**
     * Grants the given named permissions to a role on a reference.
     *
     * @param int $ref_id ref id
     * @param int $role_id role to grant the permissions to
     * @param list<string> $permissions permission operation names
     */
    private static function grantPermissions(int $ref_id, int $role_id, array $permissions): void
    {
        global $DIC;
        $review = $DIC->rbac()->review();
        $ops_ids = array_map(
            static fn(string $name): int => $review->_getOperationIdByName($name),
            $permissions
        );
        $DIC->rbac()->admin()->grantPermission($role_id, $ops_ids, $ref_id);
    }

    /**
     * Checks if the user is anonymous.
     */
    public static function isAnonymousUser(): bool
    {
        global $DIC;
        return $DIC->user()->getId() === ANONYMOUS_USER_ID;
    }
}
