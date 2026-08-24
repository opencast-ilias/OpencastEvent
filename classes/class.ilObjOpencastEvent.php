<?php

declare(strict_types=1);

use \elanev\OpencastEvent\Config\PluginConfig as LocalPluginConfig;
use srag\Plugins\Opencast\Model\Event\EventAPIRepository;
use srag\Plugins\Opencast\Container\Init;

/**
 * Class ilObjOpencastEventAccess
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
class ilObjOpencastEvent extends ilObjectPlugin
{
    protected string $table_name = ilOpencastEventPlugin::TABLE_NAME;

    private EventAPIRepository $event_repository;

    private bool $online = false;
    private string $event_id = '';
    private ?int $width = null;
    private ?int $height = null;
    private bool $new_tab = false;
    private bool $maximize = false;

    /**
     * Constructor
     *
     * @access        public
     */
    public function __construct(int $a_ref_id = 0)
    {
        $opencast_dic = Init::init();
        $this->event_repository = $opencast_dic[EventAPIRepository::class];

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
    public function doCreate(bool $clone_mode = false): void
    {
        // Getting new_tab default value from configs.
        $default_new_tab = (bool) LocalPluginConfig::getConfig(LocalPluginConfig::F_THUMBNAIL_LINK);

        $this->db->insert($this->table_name, [
            'id' => ['integer', $this->getId()],
            'is_online' => ['integer', 0],
            'event_id' => ['text', $this->getEventId()],
            'new_tab' => ['integer', $default_new_tab ? 1 : 0],
            'maximize' => ['integer', 1],
        ]);
    }

    /**
     * Read data from db
     */
    public function doRead(): void
    {
        $set = $this->db->queryF(
            'SELECT * FROM ' . $this->table_name . ' WHERE id = %s',
            ['integer'],
            [$this->getId()]
        );

        while ($rec = $this->db->fetchAssoc($set)) {
            $this->setOnline(!empty($rec["is_online"]));
            $this->setEventId($rec["event_id"]);
            $this->setWidth((int) $rec["width"]);
            $this->setHeight((int) $rec["height"]);
            $this->setNewTab(!empty($rec["new_tab"]));
            $this->setMaximize(!empty($rec["maximize"]));
        }

        $event_id = $this->getEventId();
        try {
            $event = $this->event_repository->find($event_id);
            $latest_title = $event->getTitle();
            $latest_description = $event->getDescription();
            if ($event) {
                if ($latest_title !== $this->getTitle()) {
                    $this->setTitle($latest_title);
                }
                if ($latest_description !== $this->getDescription()) {
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
        $this->db->update($this->table_name, [
            'is_online' => ['integer', (int) $this->isOnline()],
            'event_id' => ['text', $this->getEventId()],
            'new_tab' => ['integer', (int) $this->getNewTab()],
            'width' => ['integer', $this->getWidth()],
            'height' => ['integer', $this->getHeight()],
            'maximize' => ['integer', (int) $this->getMaximize()],
        ], [
            'id' => ['integer', $this->getId()],
        ]);
    }

    /**
     * Delete data from db
     */
    public function doDelete(): void
    {
        $this->db->manipulateF(
            'DELETE FROM ' . $this->table_name . ' WHERE id = %s',
            ['integer'],
            [$this->getId()]
        );
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
    public function setOnline(bool $a_val): void
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
        return $this->online;
    }

    /**
     * Set Event ID
     *
     * @param string event id
     */
    public function setEventId(string $a_val): void
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
    public function setWidth(int $a_val): void
    {
        $this->width = $a_val !== 0 ? intval($a_val) : null;
    }

    /**
     * Get Width
     *
     * @return int width
     */
    public function getWidth(): int
    {
        return empty($this->width) ? 0 : $this->width;
    }

    /**
     * Set Height
     *
     * @param int height
     */
    public function setHeight(int $a_val): void
    {
        $this->height = $a_val !== 0 ? intval($a_val) : null;
    }

    /**
     * Get Height
     *
     * @return int height
     */
    public function getHeight(): int
    {
        return empty($this->height) ? 0 : $this->height;
    }

    /**
     * Set New Tab Flag
     *
     * @param bool new tab flag
     */
    public function setNewTab(bool $a_val): void
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
        return $this->new_tab;
    }

    /**
     * Set Maximize Flag
     *
     * @param bool Maximize flag
     */
    public function setMaximize(bool $a_val): void
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
        return $this->maximize;
    }
}
