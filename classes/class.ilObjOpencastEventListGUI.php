<?php

include_once "./Services/Repository/classes/class.ilObjectPluginListGUI.php";

/**
 * Class ilObjOpencastEventListGUI
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
class ilObjOpencastEventListGUI extends ilObjectPluginListGUI
{

    /**
     * Init type
     */
    public function initType()
    {
        $this->setType(ilOpencastEventPlugin::ID);
    }

    /**
     * Get name of gui class handling the commands
     */
    public function getGuiClass()
    {
        return "ilObjOpencastEventGUI";
    }

    /**
     * Get commands
     */
    public function initCommands()
    {
        // Always set
        $this->timings_enabled = true;
        $this->subscribe_enabled = true;
        $this->payment_enabled = false;
        $this->link_enabled = true;
        $this->info_screen_enabled = true;
        $this->delete_enabled = true;
        $this->notes_enabled = true;
        $this->comments_enabled = true;

        // Should be overwritten according to status
        $this->cut_enabled = true;
        $this->copy_enabled = true;

        $commands = array(
            array(
                'permission' => 'read',
                'cmd' => 'showContent',
                'default' => true,
            ),
            array(
                'permission' => 'write',
                'cmd' => 'editEvent',
                "txt" => $this->txt('event_settings')
            )
        );

        return $commands;
    }

    /**
     * Get item properties
     *
     * @return        array                array of property arrays:
     *                                "alert" (boolean) => display as an alert property (usually in red)
     *                                "property" (string) => property name
     *                                "value" (string) => property value
     */
    public function getProperties()
    {
        global $lng, $ilUser;

        $props = array();

        $this->plugin->includeClass('class.ilObjOpencastEventAccess.php');
        if (!ilObjOpencastEventAccess::checkOnline($this->obj_id)) {
            $props[] = array(
                'alert' => true,
                'property' => $this->txt('status'),
                'value' => $this->txt('offline')
            );
        }

        return $props;
    }

    /**
    * Get all item information (title, commands, description) in HTML
    *
    * @access	public
    * @param	int			$a_ref_id		item reference id
    * @param	int			$a_obj_id		item object id
    * @param	int			$a_title		item title
    * @param	int			$a_description	item description
    * @param	bool		$a_use_asynch
    * @param	bool		$a_get_asynch_commands
    * @param	string		$a_asynch_url
    * @param	bool		$a_context	    workspace/tree context
    * @return	string		html code
    */
    public function getListItemHTML(
        $a_ref_id,
        $a_obj_id,
        $a_title,
        $a_description,
        $a_use_asynch = false,
        $a_get_asynch_commands = false,
        $a_asynch_url = "",
        $a_context = self::CONTEXT_REPOSITORY
    ) {
        $event_obj = new ilObjOpencastEvent($a_ref_id);

        $latest_title = $event_obj->getTitle();
        $latest_description = $event_obj->getDescription();

        return parent::getListItemHTML($a_ref_id, $a_obj_id, $latest_title, $latest_description, $a_use_asynch, $a_get_asynch_commands, $a_asynch_url, $a_context);
    }
}
