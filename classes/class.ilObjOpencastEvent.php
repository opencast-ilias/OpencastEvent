<?php

include_once("./Services/Repository/classes/class.ilObjectPlugin.php");
require_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/OpencastEvent/classes/class.ilObjOpencastEventGUI.php");
use srag\Plugins\Opencast\Model\Event\EventAPIRepository;
use srag\Plugins\Opencast\DI\OpencastDIC;
use srag\Plugins\Opencast\Model\Config\PluginConfig;

/**
 * Class ilObjOpencastEventAccess
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
class ilObjOpencastEvent extends ilObjectPlugin
{
    protected $table_name;

    /** @var EventAPIRepository*/
    private $event_repository;

    /**
     * Constructor
     *
     * @access        public
     * @param int $a_ref_id
     */
    public function __construct($a_ref_id = 0)
    {
        global $opencastContainer;
        $this->table_name = ilOpencastEventPlugin::TABLE_NAME;
        $opencast_dic = OpencastDIC::getInstance();
        $this->event_repository = $opencastContainer[EventAPIRepository::class];
        PluginConfig::setApiSettings();
        parent::__construct($a_ref_id);
    }

    /**
     * Get type.
     */
    final public function initType(): void
    {
        $this->setType(ilOpencastEventPlugin::ID);
    }

    /**
     * Create object
     */
    public function doCreate(): void
    {
        global $ilDB;

        $values = [
            $ilDB->quote($this->getId(), "integer"),
            $ilDB->quote(0, "integer"),
            $ilDB->quote($this->getEventId(), "string"),
            $ilDB->quote(1, "integer"),
            $ilDB->quote(1, "integer"),
        ];

        $insert_sql = "INSERT INTO {$this->table_name} (id, is_online, event_id, new_tab, maximize) VALUES (" . implode(', ', $values) . ")";

        $ilDB->manipulate($insert_sql);
    }

    /**
     * Read data from db
     */
    public function doRead(): void
    {
        global $ilDB;

        $object_id = $ilDB->quote($this->getId(), "integer");
        $select_sql = "SELECT * FROM {$this->table_name} WHERE id = $object_id";

        $set = $ilDB->query($select_sql);

        while ($rec = $ilDB->fetchAssoc($set)) {
            $this->setOnline($rec["is_online"]);
            $this->setEventId($rec["event_id"]);
            $this->setWidth($rec["width"]);
            $this->setHeight($rec["height"]);
            $this->setNewTab($rec["new_tab"]);
            $this->setMaximize($rec["maximize"]);
        }

        $event_id = $this->getEventId();
        try {
            $event = $this->event_repository->find($event_id);
            $latest_title = $event->getTitle();
            $latest_description = $event->getDescription();
            if ($event) {
                if ($latest_title != $this->getTitle()) {
                    $this->setTitle($latest_title);
                }
                if ($latest_description != $this->getDescription()) {
                    $this->setDescription($latest_description);
                }
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Update data
     */
    public function doUpdate(): void
    {
        global $ilDB;

        $values = [
            'is_online = ' . $ilDB->quote($this->isOnline(), "integer"),
            'event_id = ' . $ilDB->quote($this->getEventId(), "string"),
            'new_tab = ' . $ilDB->quote($this->getNewTab(), "integer"),
            'width = ' . $ilDB->quote($this->getWidth(), "integer"),
            'height = ' . $ilDB->quote($this->getHeight(), "integer"),
            'maximize = ' . $ilDB->quote($this->getMaximize(), "integer"),
        ];
        $object_id = $ilDB->quote($this->getId(), "integer");

        $update_sql = "UPDATE {$this->table_name} SET " . implode(', ', $values) . " WHERE id = $object_id";
        $ilDB->manipulate($up = $update_sql);
    }

    /**
     * Delete data from db
     */
    public function doDelete(): void
    {
        global $ilDB;

        $object_id = $ilDB->quote($this->getId(), "integer");

        $delete_sql = "DELETE FROM {$this->table_name} WHERE id = $object_id";
        $ilDB->manipulate($delete_sql);
    }

    /**
     * Do Cloning
     */
    public function doCloneObject($new_obj, $a_target_id, $a_copy_id = null): void
    {
        $new_obj->setOnline($this->isOnline());
        $new_obj->setEventId($this->getEventId());
        $new_obj->setNewTab($this->getNewTab());
        $new_obj->setMaximize($this->getMaximize());
        $new_obj->setWidth($this->getWidth());
        $new_obj->setHeight($this->getHeight());
        $new_obj->update();
    }

    /**
     * Set online
     *
     * @param bool online
     */
    public function setOnline($a_val): void
    {
        $this->online = $a_val;
    }

    /**
     * Get online
     *
     * @return bool online
     */
    public function isOnline(): bool
    {
        return $this->online ? true : false;
    }

    /**
     * Set Event ID
     *
     * @param string event id
     */
    public function setEventId($a_val): void
    {
        $this->event_id = $a_val;
    }

    /**
     * Get Event ID
     *
     * @return string event id
     */
    public function getEventId(): string
    {
        return $this->event_id;
    }

    /**
     * Set Width
     *
     * @param int width
     */
    public function setWidth($a_val): void
    {
        $this->width = $a_val ? intval($a_val) : null;
    }

    /**
     * Get Width
     *
     * @return int width
     */
    public function getWidth(): int
    {
        return !empty($this->width) ? $this->width : 0;
    }

    /**
     * Set Height
     *
     * @param int height
     */
    public function setHeight($a_val): void
    {
        $this->height = $a_val ? intval($a_val) : null;
    }

    /**
     * Get Height
     *
     * @return int height
     */
    public function getHeight(): int
    {
        return !empty($this->height) ? $this->height : 0;
    }

    /**
     * Set New Tab Flag
     *
     * @param int new tab flag
     */
    public function setNewTab($a_val): void
    {
        $this->new_tab = $a_val;
    }

    /**
     * Get New Tab Flag
     *
     * @return bool new tab flag
     */
    public function getNewTab(): bool
    {
        return $this->new_tab ? true : false;
    }

    /**
     * Set Maximize Flag
     *
     * @param int Maximize flag
     */
    public function setMaximize($a_val): void
    {
        $this->maximize = $a_val;
    }

    /**
     * Get Maximize Flag
     *
     * @return bool Maximize flag
     */
    public function getMaximize(): bool
    {
        return $this->maximize ? true : false;
    }
}
