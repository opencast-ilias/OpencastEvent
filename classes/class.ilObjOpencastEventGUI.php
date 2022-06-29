<?php

include_once('./Services/Repository/classes/class.ilObjectPluginGUI.php');
require_once('./Services/Form/classes/class.ilPropertyFormGUI.php');
require_once('./Services/Form/classes/class.ilTextInputGUI.php');
require_once('./Services/Form/classes/class.ilCheckboxInputGUI.php');
require_once('./Customizing/global/plugins/Services/Repository/RepositoryObject/OpencastEvent/classes/class.ilOpencastEventPlugin.php');
require_once('./Customizing/global/plugins/Services/Repository/RepositoryObject/OpencastEvent/classes/class.ilObjOpencastEventAccess.php');
require_once('./Customizing/global/plugins/Services/Repository/RepositoryObject/OpencastEvent/classes/class.ilObjOpencastEvent.php');
require_once('./Customizing/global/plugins/Services/Repository/RepositoryObject/OpenCast/classes/class.ilOpenCastPlugin.php');
use srag\Plugins\Opencast\DI\OpencastDIC;
use srag\Plugins\Opencast\Model\Config\PluginConfig;
use srag\Plugins\Opencast\Util\Player\PlayerDataBuilderFactory;
use srag\Plugins\Opencast\Model\Event\Event;
use srag\Plugins\Opencast\Model\User\xoctUser;

/**
 * Class ilObjOpencastEventGUI
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 *
 * @ilCtrl_isCalledBy ilObjOpencastEventGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls ilObjOpencastEventGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI
 */
class ilObjOpencastEventGUI extends ilObjectPluginGUI
{
    public const DEFAULT_WIDTH = 320;
    public const DEFAULT_HEIGHT = 180;
    public const DEFAULT_LIMIT = 10;

    /** @var Container */
    protected $dic;

    /** @var  ilCtrl */
    protected $ctrl;

    /** @var  ilTabsGUI */
    protected $tabs;

    /** @var  ilTemplate */
    public $tpl;

    /** @var ilTree */
    public $tree;

    /** @var EventAPIRepository*/
    private $event_repository;

    /** @var ilOpenCastPlugin */
    private $opencast_plugin;

    /** @var PaellaConfigServiceFactory */
    private $paellaConfigServiceFactory;

    /** @var PaellaConfigService */
    private $paellaConfigService;

    /**
     * Initialisation
     */
    protected function afterConstructor(): void
    {
        global $DIC;
        $this->dic = $DIC;
        $this->ctrl = $DIC->ctrl();
        $this->tabs = $DIC->tabs();
        $this->tree = $DIC->repositoryTree();
        $this->tpl = $DIC['tpl'];
        $this->opencast_plugin = ilOpenCastPlugin::getInstance();
        $opencast_dic = OpencastDIC::getInstance();
        $this->event_repository = $opencast_dic->event_repository();
        $this->paellaConfigServiceFactory = $opencast_dic->paella_config_service_factory();
        $this->paellaConfigService = $this->paellaConfigServiceFactory->get();
        PluginConfig::setApiSettings();
    }

    /**
     * Get type.
     */
    final public function getType(): string
    {
        return ilOpencastEventPlugin::ID;
    }

    /**
     * Handles all commmands of this class, centralizes permission checks
     */
    public function performCommand($cmd): void
    {
        switch ($cmd) {
            case 'create':
            case 'save':
            case 'delete':
            case 'editEvent':
            case 'updateEvent':
            case 'saveEvent':
                $this->checkPermission('write');
                $this->$cmd();
                break;
            case 'showContent':
            case 'streamVideo':
            default:
                $this->checkPermission('read');
                $this->$cmd();
                break;
        }
    }

    /**
     * executeCommand
     */
    public function executeCommand(): void
    {
        $this->setHeaderOfflineStatus();
        parent::executeCommand();
    }

    /**
     * Shows offline status in the header.
     */
    protected function setHeaderOfflineStatus(): void
    {
        if ($this->object &&
            $this->object instanceof ilObjOpencastEvent &&
            !$this->object->isOnline()) {
            $this->tpl->setAlertProperties(array(
                array("alert" => true,
                    "property" => $this->lng->txt("status"),
                    "value" => $this->lng->txt("offline"))
            ));
        }
    }

    /**
     * Command to be performed after creation.
    */
    public function getAfterCreationCmd(): string
    {
        return 'editEvent';
    }

    /**
     * Command to be performed by default.
    */
    public function getStandardCmd(): string
    {
        return 'showContent';
    }

    /**
     * Define the creation form content.
     *
     * The reason to have this methos here, is to check if the object is being used in course/group.
     */
    protected function initCreationForms($a_new_type): array
    {
        // To prevent using it out of course or groups.
        if (!$this->checkParentGroupCourse($_GET['ref_id'])) {
            ilUtil::sendFailure($this->txt('msg_creation_failed'), true);
            $this->ctrl->redirectByClass('ilDashboardGUI', '');
        }

        $forms = array(
            self::CFORM_NEW => $this->initCreateForm($a_new_type),
        );

        return $forms;
    }

    ///////////////////
    // DISPLAY TABS //
    //////////////////

    /**
     * Sets the tab for this plugin.
     */
    public function setTabs(): void
    {
        global $ilAccess;

        if ($ilAccess->checkAccess('read', '', $this->object->getRefId())) {
            $this->tabs->addTab('content', $this->txt('show_event'), $this->ctrl->getLinkTarget($this, 'showContent'));
        }

        if ($ilAccess->checkAccess('write', '', $this->object->getRefId())) {
            $this->tabs->addTab('event_settings', $this->txt('event_settings'), $this->ctrl->getLinkTarget($this, 'editEvent'));
        }

        $this->addInfoTab();
        $this->addPermissionTab();
    }

    /////////////
    // actions //
    /////////////

    /**
     * This method is called after create action.
     *
     * It is meant to check the creation form, create the opencast event object and perform afterSave method.
     * It returns to create action if the requirments are not met.
     */
    protected function saveEvent(): void
    {
        $this->ctrl->setParameter($this, 'new_type', $this->getType());

        $form = $this->initEventForm(true);
        if ($this->checkInput($form)) {
            $this->ctrl->setParameter($this, 'new_type', '');

            $newEventObj = $this->createOpencastEventObject($form);
            if (!empty($newEventObj)) {
                ilUtil::sendSuccess($this->txt('create_successful'), true);

                $args = func_get_args();
                if ($args) {
                    $this->afterSave($newEventObj, $args);
                } else {
                    $this->afterSave($newEventObj);
                }
                return;
            } else {
                ilUtil::sendFailure($this->txt('msg_creation_failed'));
            }
        }

        $this->ctrl->redirect($this, 'create');
    }

    /**
     * It overwrite the public create method of the parents,
     * in order for us to provide a custom form.
     */
    public function create(): void
    {
        $form = $this->initEventForm();
        $table = $this->getTable();

        $this->renderEventForm($form, $table);
    }

    /**
     * This method is used to display a custom event object edit form.
     */
    protected function editEvent(): void
    {
        $this->tabs->activateTab('event_settings');
        $form = $this->initEventForm(false);
        $this->addValuesToForm($form);
        $table = $this->getTable(false);

        $this->renderEventForm($form, $table, false);
    }

    /**
     * This method is used to update the event object, and redirects back to editEvent action.
     */
    protected function updateEvent(): void
    {
        $form = $this->initEventForm(false);
        if ($this->checkInput($form)) {
            $this->updateOpencastEventObject($form);
            ilUtil::sendSuccess($this->txt('update_successful'), true);
        }

        $this->ctrl->redirect($this, 'editEvent');
    }

    /**
     * This is the main index action of the plugin, to display the opencast event object.
     */
    protected function showContent(): void
    {
        $this->tabs->activateTab('content');
        $content_html = '';
        $event_id = $this->object->getEventId();
        $event = $this->getEvent($event_id);
        if (!empty($event)) {
            $this->tpl->addCss($this->getPlugin()->getDirectory() . '/templates/css/player.min.css');
            $this->tpl->addJavaScript($this->getPlugin()->getDirectory() . '/templates/js/player.min.js');
            $this->tpl->addOnLoadCode('il.OpencastEvent.player.init(' .
                json_encode($this->getPlayerJSConfig($event)) .
            ');');
            $stream_url = $this->ctrl->getLinkTarget($this, 'streamVideo');
            $tpl_name = $this->object->getNewTab() ? 'tpl.OpencastEventPlayer.html' : 'tpl.OpencastEventPlayerEmbed.html';
            $tpl = new ilTemplate($this->getPlugin()->getDirectory() . '/templates/html/' . $tpl_name, true, true);
            if ($this->object->getNewTab()) {
                $tpl->setVariable('VIDEO_LINK', $stream_url);
                $tpl->setVariable('THUMBNAIL_URL', $event->publications()->getThumbnailUrl());
                $tpl->setVariable('OVERLAY_ICON_URL', $this->getPlugin()->getDirectory() . '/templates/images/play.svg');
            } else {
                $tpl->setVariable('URL', $stream_url);
            }
            $content_html = $tpl->get();
        }

        $this->tpl->setContent($content_html);
    }

    /**
     * This method is meant to call by the iframe in order to provide the Paella player source code.
     */
    public function streamVideo(): void
    {
        $event_id = $this->object->getEventId();
        $event = $this->getEvent($event_id);
        if (empty($event)) {
            exit;
        }

        if (!PluginConfig::getConfig(PluginConfig::F_INTERNAL_VIDEO_PLAYER) && !$event->isLiveEvent()) {
            // redirect to opencast
            header('Location: ' . $event->publications()->getPlayerLink());
            exit;
        }

        try {
            $data = PlayerDataBuilderFactory::getInstance()->getBuilder($event)->buildStreamingData();
        } catch (Exception $e) {
            ilUtil::sendFailure($e->getMessage());
            echo $e->getMessage();
            exit;
        }

        $paella_player_tpl = $this->opencast_plugin->getTemplate('paella_player.html', true, true);
        $paella_player_tpl->setVariable('TITLE', $event->getTitle());
        $paella_player_tpl->setVariable('PAELLA_PLAYER_FOLDER', $this->opencast_plugin->getDirectory()
            . '/node_modules/paellaplayer/build/player');
        $paella_player_tpl->setVariable('DATA', json_encode($data));
        $paella_player_tpl->setVariable('JS_CONFIG', json_encode($this->buildJSConfig($event)));

        if ($event->isLiveEvent()) {
            $paella_player_tpl->setVariable('LIVE_WAITING_TEXT', $this->opencast_plugin->translate(
                'live_waiting_text',
                'event',
                [date('H:i', $event->getScheduling()->getStart()->getTimestamp())]
            ));
            $paella_player_tpl->setVariable('LIVE_INTERRUPTED_TEXT', $this->opencast_plugin->translate('live_interrupted_text', 'event'));
            $paella_player_tpl->setVariable('LIVE_OVER_TEXT', $this->opencast_plugin->translate('live_over_text', 'event'));
        }

        $paella_player_tpl->setVariable('STYLE_SHEET_LOCATION', ILIAS_HTTP_PATH . '/' . $this->opencast_plugin->getDirectory() . '/templates/default/player.css');
        setcookie('lastProfile', null, -1);
        echo $paella_player_tpl->get();
        exit();
    }

    //////////////////////
    // Helper functions //
    //////////////////////

    /**
     * Creates the config object for the Paella player.
     *
     * @param Event $event the Event object
     *
     * @return stdClass $js_config
     */
    protected function buildJSConfig(Event $event): stdClass
    {
        $js_config = new stdClass();
        $paella_config = $this->paellaConfigService->getEffectivePaellaPlayerUrl($event->isLiveEvent());
        $js_config->paella_config_file = $paella_config['url'];
        $js_config->paella_config_info = $paella_config['info'];
        $js_config->paella_config_is_warning = $paella_config['warn'];
        $js_config->paella_player_folder = $this->opencast_plugin->getDirectory() . '/node_modules/paellaplayer/build/player';

        if ($event->isLiveEvent()) {
            $js_config->check_script_hls = $this->opencast_plugin->directory() . '/src/Util/check_hls_status.php'; // script to check live stream availability
            $js_config->is_live_stream = true;
            $js_config->event_start = $event->getScheduling()->getStart()->getTimestamp();
            $js_config->event_end = $event->getScheduling()->getEnd()->getTimestamp();
        }
        return $js_config;
    }

    /**
     * Get the config for the player.
     *
     * @return stdClass $js_config
     */
    protected function getPlayerJSConfig(): stdClass
    {
        $js_config = new stdClass();
        $js_config->maximize = $this->object->getMaximize();
        $js_config->width = $this->object->getWidth() ? $this->object->getWidth() : self::DEFAULT_WIDTH;
        $js_config->height = $this->object->getHeight() ? $this->object->getHeight() : self::DEFAULT_HEIGHT;
        return $js_config;
    }

    /**
     * Helper function to create the Opencast Event Object
     *
     * @param ilPropertyFormGUI $form
     *
     * @return ilObjOpencastEvent $newObj or null if event object cannot be found from xoct
     */
    private function createOpencastEventObject($form): ?ilObjOpencastEvent
    {
        // create instance
        $event = $this->getEvent($form->getInput('event_id'));
        if (empty($event)) {
            return null;
        }
        $objDefinition = $this->dic['objDefinition'];
        $class_name = 'ilObj' . $objDefinition->getClassName($this->getType());
        $location = $objDefinition->getLocation($this->getType());
        include_once($location . '/class.' . $class_name . '.php');
        $newObj = new $class_name();
        $newObj->setType($this->getType());
        $newObj->setTitle($event->getTitle());
        $newObj->setDescription($event->getDescription());
        $newObj->setEventId($form->getInput('event_id'));
        $newObj->create();

        if ($newObj) {
            $parent_id = $this->node_id;
            $this->node_id = null;
            $this->putObjectInTree($newObj, $parent_id);

            // apply didactic template?
            $dtpl = $this->getDidacticTemplateVar('dtpl');
            if ($dtpl) {
                $newObj->applyDidacticTemplate($dtpl);
            }

            // auto rating
            $this->handleAutoRating($newObj);

            // set default permissions
            ilObjOpencastEventAccess::setDefaultPerms($newObj->getRefId());
        }

        return $newObj;
    }

    /**
     * Helper function to update the Opencast Event Object
     *
     * @param ilPropertyFormGUI $form
     */
    private function updateOpencastEventObject($form): void
    {
        $event = $this->getEvent($form->getInput('event_id'));
        if (empty($event)) {
            return;
        }
        $this->object->setTitle($event->getTitle());
        $this->object->setDescription($event->getDescription());
        $this->object->setOnline($form->getInput('online') ? true : false);
        $this->object->setEventId($form->getInput('event_id'));
        $this->object->setNewTab($form->getInput('new_tab') ? true : false);
        $this->object->setMaximize($form->getInput('size_type') == 'maximize' ? true : false);
        if ($form->getInput('size_type') == 'custom') {
            $embed_size = $form->getInput('embed_size');
            $this->object->setWidth($embed_size['width']);
            $this->object->setHeight($embed_size['height']);
        }
        $this->object->update();
    }

    /**
     * Helper function to render the edit/create form
     *
     * @param ilPropertyFormGUI $form
     * @param OpencastEventListTableGUI $table
     * @param bool $is_new determine the state of the form to be rendered for create or editEvent action
     */
    private function renderEventForm($form, $table, $is_new = true): void
    {
        $event_tpl = new ilTemplate($this->getPlugin()->getDirectory() . '/templates/html/tpl.OpencastEventCreate.html', true, true);

        $event_tpl->setCurrentBlock('form');
        $footer_replace_tpl = new ilTemplate($this->getPlugin()->getDirectory() . '/templates/html/tpl.OpencastEventFooterReplace.html', true, false);
        $event_table_replace_tpl = new ilTemplate($this->getPlugin()->getDirectory() . '/templates/html/tpl.OpencastEventFormEventTableReplace.html', true, true);
        $event_table_replace_tpl->setVariable('TABLE', $table->getHTML());
        $event_table_replace_tpl->setVariable('FOOTER', $footer_replace_tpl->get());

        $form_with_table = str_replace($footer_replace_tpl->get(), $event_table_replace_tpl->get(), $form->getHTML());
        $event_tpl->setVariable('FORM', $form_with_table);
        $event_tpl->parseCurrentBlock();

        if (!$is_new) {
            $this->tpl->addJavaScript($this->getPlugin()->getDirectory() . '/templates/js/ion.rangeSlider.min.js');
            $this->tpl->addCss($this->getPlugin()->getDirectory() . '/templates/css/ion.rangeSlider.min.css');
            $this->tpl->addJavaScript($this->getPlugin()->getDirectory() . '/templates/js/form.min.js');
            $cons_prop_text = $this->lng->txt('cont_constrain_proportions', 'content');
            $slider_config = json_encode($this->getRangeSliderConfig());
            $this->tpl->addOnLoadCode('il.OpencastEvent.form.initForm(' .
                self::DEFAULT_WIDTH * 2 . ', "' . $cons_prop_text . '", ' . $slider_config .
            ');');
        }
        $this->tpl->addCss($this->getPlugin()->getDirectory() . '/templates/css/table.min.css');
        $this->tpl->addJavaScript($this->getPlugin()->getDirectory() . '/templates/js/table.min.js');
        $this->tpl->addOnLoadCode('il.OpencastEvent.table.init();');

        $this->tpl->setContent($event_tpl->get());
    }
    /**
     * Helper function to custom checks the form input.
     *
     * @param ilPropertyFormGUI $form
     *
     * @return bool based on the checkers defined, returns true of false.
     */
    protected function checkInput($form): bool
    {
        $return = $form->checkInput();

        // We need event_id in any case!
        $event_id = $form->getInput('event_id');
        if (empty($event_id)) {
            ilUtil::sendFailure($this->txt('no_event_id'), true);
            return false;
        }

        return $return;
    }

    /**
     * Helper function to create the form object. Different forms may be created based of the param.
     *
     * @param bool $is_new to determine if the form is used for edit or create action.
     *
     * @return ilPropertyFormGUI $form
     */
    protected function initEventForm($is_new = true): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();

        if ($is_new) {
            $form->setTitle($this->txt('obj_' . $this->getType()));
            $form->setId($this->getType() . '_event_new');

            $event_id = new ilHiddenInputGUI('event_id');
            $form->addItem($event_id);

            $form->setFormAction($this->ctrl->getFormAction($this, 'saveEvent'));

            $form->addCommandButton('saveEvent', $this->txt($this->getType() . '_new'));
        } else {
            $form->setId($this->getType() . '_event_edit');

            //1. Settings Section
            $setting_section = new ilFormSectionHeaderGUI();
            $setting_section->setTitle($this->txt('settings_section'));
            $form->addItem($setting_section);

            $online = new ilCheckboxInputGUI($this->txt('online'), 'online');
            $online->setInfo($this->txt('online_info'));
            $form->addItem($online);

            $size_type = new ilRadioGroupInputGUI($this->txt('size_type'), "size_type");
            $size_type->setRequired(true);
            $form->addItem($size_type);

            $size_type_maximize = new ilRadioOption($this->txt('maximize'), 'maximize');
            $size_type_maximize->setInfo($this->txt('maximize_info'));
            $size_type->addOption($size_type_maximize);

            $size_type_custom = new ilRadioOption($this->txt('custome_size'), 'custom');
            $size_type_custom->setInfo($this->txt('custome_size_info'));
            $size_type->addOption($size_type_custom);

            // preview image
            $preview_image = new ilNonEditableValueGUI($this->opencast_plugin->txt('event_preview'), '', true);
            $preview_image_width = $this->object->getWidth() ? $this->object->getWidth() : self::DEFAULT_WIDTH;
            $preview_image_height = $this->object->getHeight() ? $this->object->getHeight() : self::DEFAULT_HEIGHT;
            $preview_image_tpl = new ilTemplate($this->getPlugin()->getDirectory() . '/templates/html/tpl.OpencastEventPreviewImage.html', false, false);
            $preview_image_tpl->setVariable('DYNAMIC_WIDTH', $preview_image_width);
            $preview_image_tpl->setVariable('DYNAMIC_HEIGHT', $preview_image_height);
            $event_id = $this->object->getEventId();
            $event = $this->getEvent($event_id);
            if (!empty($event)) {
                $preview_image_tpl->setVariable('SRC', $event->publications()->getThumbnailUrl());
            }
            $preview_image->setValue($preview_image_tpl->get());
            $size_type_custom->addSubItem($preview_image);

            // width height
            $width_height = new ilWidthHeightInputGUI($this->txt("height_width"), 'embed_size');
            $width_height->setRequired(true);
            $width_height->setConstrainProportions(true);
            $size_type_custom->addSubItem($width_height);

            // slider
            $slider = new ilNonEditableValueGUI('', '', true);
            $slider_tpl = new ilTemplate($this->getPlugin()->getDirectory() . '/templates/html/tpl.OpencastEventInputSlider.html', false, false);
            $slider->setValue($slider_tpl->get());
            $size_type_custom->addSubItem($slider);

            $new_tab = new ilCheckboxInputGUI($this->txt('new_tab'), 'new_tab');
            $new_tab->setInfo($this->txt('new_tab_info'));
            $form->addItem($new_tab);

            //2. Opencast Video Section
            $oc_video_section = new ilFormSectionHeaderGUI();
            $oc_video_section->setTitle($this->txt('oc_video_section'));
            $form->addItem($oc_video_section);

            $title = new ilNonEditableValueGUI($this->txt('title'), 'title');
            $title->setInfo($this->txt('title_info'));
            $form->addItem($title);

            $event_id = new ilHiddenInputGUI('event_id');
            $form->addItem($event_id);

            $event_id_display = new ilNonEditableValueGUI($this->txt('event_id'), 'event_id_display');
            $form->addItem($event_id_display);

            $change_event = new ilCheckboxInputGUI($this->txt('change_event'), 'change_event');
            $event_id_display->addSubItem($change_event);

            $current_title = new ilHiddenInputGUI('current_title');
            $form->addItem($current_title);

            $current_event_id = new ilHiddenInputGUI('current_event_id');
            $form->addItem($current_event_id);

            $form->setFormAction($this->ctrl->getFormAction($this, 'updateEvent'));

            $form->addCommandButton('updateEvent', $this->txt($this->getType() . '_write'));
        }

        return $form;
    }

    /**
     * @return array
     */
    private function getRangeSliderConfig(): array
    {
        return [
            'skin' => 'modern',
            'min' => 0,
            'max' => 100,
            'from' => 50,
            'from_min' => 10,
            'step' => 1,
            'grid' => true,
            'postfix' => '%',
        ];
    }

    /**
     * Helper function to add values to the edit form.
     *
     * @param $form ilPropertyFormGUI
     */
    protected function addValuesToForm(&$form): void
    {
        $values_array = [
            'title' => $this->object->getTitle(),
            'description' => $this->object->getDescription(),
            'online' => $this->object->isOnline(),
            'event_id' => $this->object->getEventId(),
            'event_id_display' => $this->object->getEventId(),
            'current_title' => $this->object->getTitle(),
            'current_event_id' => $this->object->getEventId(),
            'embed_size' => [
                'width' => $this->object->getWidth() ? $this->object->getWidth() : self::DEFAULT_WIDTH,
                'height' => $this->object->getHeight() ? $this->object->getHeight() : self::DEFAULT_HEIGHT,
                'constr_prop' => true
            ],
            'new_tab' => $this->object->getNewtab()
        ];
        $size_type = $this->object->getMaximize() ? 'maximize' : 'custom';
        $values_array['size_type'] = $size_type;
        if ($_GET['change_event']) {
            $values_array['change_event'] = true;
        }
        $form->setValuesByArray($values_array);
    }


    /**
     * Helper function to get the opencast event list table.
     *
     * @param bool $is_new to determine if the form is used for edit or create action.
     *
     * @return OpencastEventListTableGUI
     */
    protected function getTable($is_new = true): OpencastEventListTableGUI
    {
        include_once($this->getPlugin()->getDirectory() . '/classes/Table/OpencastEventListTableGUI.php');
        $opencast_event_table = new OpencastEventListTableGUI($this, $is_new ? 'create' : 'editEvent', (int) $_GET['ref_id']);

        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        $opencast_event_table->setOffset($offset);

        $apply_filter_cmd = $is_new ? 'applyFilter' : 'applyFilterEdit';
        $reset_filter_cmd = $is_new ? 'resetFilter' : 'resetFilterEdit';
        $opencast_event_table->setFilterCommand($apply_filter_cmd);
        $opencast_event_table->setResetCommand($reset_filter_cmd);

        return $this->handleTable($opencast_event_table, $is_new);
    }

    /**
     * Helper function to set table data and properties accordingly.
     *
     * @param OpencastEventListTableGUI $opencast_event_table the opencast event table.
     * @param bool $is_new to determine if the form is used for edit or create action.
     *
     * @return OpencastEventListTableGUI
     */
    private function handleTable($opencast_event_table, $is_new): OpencastEventListTableGUI
    {
        $limit = self::DEFAULT_LIMIT;
        $filter = $opencast_event_table->buildFilterArray();
        $offset = $opencast_event_table->getOffset();
        $sort_direction = $opencast_event_table->getOrderDirection();
        $sort_field = $opencast_event_table->getOrderField();
        $sort_field = $sort_field == 'series' ? 'series_name' : $sort_field;
        $sort_field = $sort_field == 'start' ? 'start_date' : $sort_field;
        $avialable_sorts = ['title', 'location', 'series_name', 'start_date'];
        $sort = '';
        if (in_array($sort_field, $avialable_sorts)) {
            $sort = "$sort_field:$sort_direction";
        }

        $events = $this->getEvents($filter, $offset, $limit, $sort);

        $max_size = ($offset + 1) * $limit;
        $has_next = false;
        $has_prev = $offset > 0 ? true : false;
        if (count($events) == ($limit + 1)) {
            $has_next = true;
            $max_size = ($offset + 1) * count($events);
            array_pop($events);
        }

        $cmd = 'editEvent';
        if ($is_new) {
            $cmd = 'create';
            $this->ctrl->setParameter($this, 'new_type', $this->getType());
        } else {
            $this->ctrl->setParameter($this, 'change_event', true);
        }
        $custom_prev_cmd = '';
        $custom_next_cmd = '';
        if ($has_next) {
            $this->ctrl->setParameter($this, 'offset', ($offset + 1));
            $custom_next_cmd = $this->ctrl->getLinkTarget($this, $cmd);
        }
        if ($has_prev) {
            $this->ctrl->setParameter($this, 'offset', ($offset - 1));
            $custom_prev_cmd = $this->ctrl->getLinkTarget($this, $cmd);
        }
        if ($has_next || $has_prev) {
            $opencast_event_table->setCustomPreviousNext($custom_prev_cmd, $custom_next_cmd);
        }

        $opencast_event_table->setData($events);
        $opencast_event_table->setMaxCount($max_size);

        return $opencast_event_table;
    }

    /**
     * Helper function to apply filters of the table when it is rendered for create action.
     * It redirects back to create action
     */
    public function applyFilter(): void
    {
        $table = $this->getTable();
        $this->performApplyFilter($table);
        $this->ctrl->setParameter($this, 'new_type', $this->getType());
        $this->ctrl->redirect($this, 'create');
    }

    /**
     * Helper function to apply filters of the table when it is rendered for editEvent action.
     * It redirects back to editEvent action
     */
    public function applyFilterEdit(): void
    {
        $table = $this->getTable(false);
        $this->performApplyFilter($table);
        $this->ctrl->setParameter($this, 'change_event', true);
        $this->ctrl->redirect($this, 'editEvent');
    }

    /**
     * Helper function to perform applying filters to the table.
     *
     * @param OpencastEventListTableGUI $table
     */
    private function performApplyFilter($table): void
    {
        $table->resetOffset();
        $table->storeProperty('offset', 0);
        $this->ctrl->setParameter($this, 'offset', 0);
        $table->writeFilterToSession();
    }

    /**
     * Helper function to reset filters of the table when it is rendered for create action.
     * It redirects back to create action.
     */
    public function resetFilter(): void
    {
        $table = $this->getTable();
        $this->performResetFilter($table);
        $this->ctrl->setParameter($this, 'new_type', $this->getType());
        $this->ctrl->redirect($this, 'create');
    }

    /**
     * Helper function to reset filters of the table when it is rendered for editEvent action.
     * It redirects back to editEvent action
     */
    public function resetFilterEdit(): void
    {
        $table = $this->getTable(false);
        $this->performResetFilter($table);
        $this->ctrl->setParameter($this, 'change_event', true);
        $this->ctrl->redirect($this, 'editEvent');
    }

    /**
     * Helper function to perform resetting filters for the table.
     *
     * @param OpencastEventListTableGUI $table
     */
    private function performResetFilter($table): void
    {
        $table->resetOffset();
        $table->storeProperty('offset', 0);
        $this->ctrl->setParameter($this, 'offset', 0);
        $table->resetFilter();
    }

    /**
     * Gets the opencast event plugin
     * @return ilOpencastEventPlugin
     */
    public function getPlugin(): ilOpencastEventPlugin
    {
        return parent::getPlugin();
    }

    /**
     * Checks if the parent object is course or group, to prevent access otherwise!
     *
     * @param string $ref_id
     * @return bool
     */
    private function checkParentGroupCourse($ref_id): bool
    {
        $is_checked = false;
        if ($this->tree->checkForParentType($ref_id, 'grp') > 0 ||
            $this->tree->checkForParentType($ref_id, 'crs') > 0) {
            $is_checked = true;
        }
        return $is_checked;
    }

    /**
     * Gets the events available to user to fill the table.
     *
     * @return array $events events list
     */
    private function getEvents($filter = [], $offset = 0, $limit = 1000, $sort = ''): array
    {
        // the api doesn't deliver a max count, so we fetch (limit + 1) to see if there should be a 'next' page
        try {
            $common_idp = PluginConfig::getConfig(PluginConfig::F_COMMON_IDP);
            $events = (array) $this->event_repository->getFiltered(
                $filter,
                $common_idp ? xoctUser::getInstance($this->dic->user())->getIdentifier() : '',
                $common_idp ? [] : [xoctUser::getInstance($this->dic->user())->getUserRoleName()],
                $offset,
                $limit + 1,
                $sort
            );
        } catch (Exception $e) {
            $events = [];
            if ($e->getCode() !== 403) {
                ilUtil::sendFailure($e->getMessage());
            }
        }
        return $events;
    }

    /**
     * Gets the event object from xoct EventAPIRepository
     *
     * @param string $event_id the event identifier
     *
     * @return srag\Plugins\Opencast\Model\Event\Event|null return Event object or null if something went wrong
     */
    private function getEvent($event_id): ?Event
    {
        $event = null;
        try {
            $event = $this->event_repository->find($event_id);
        } catch (Exception $e) {
            ilUtil::sendFailure($e->getMessage());
        }
        return $event;
    }
}
