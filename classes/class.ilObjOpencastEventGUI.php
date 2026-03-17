<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
use elanev\OpencastEvent\Listing\OpencastEventListing;
use srag\Plugins\Opencast\Container\Init;
use srag\Plugins\Opencast\Model\Config\PluginConfig;
use srag\Plugins\Opencast\Model\Event\Event;
use srag\Plugins\Opencast\Model\Event\EventAPIRepository;
use srag\Plugins\Opencast\Model\Metadata\Definition\MDFieldDefinition;
use srag\Plugins\Opencast\Model\Series\SeriesRepository;
use srag\Plugins\Opencast\Model\Series\SeriesAPIRepository;
use srag\Plugins\Opencast\Model\User\xoctUser;
use srag\Plugins\Opencast\Util\Player\PlayerDataBuilderFactory;
use srag\Plugins\Opencast\Util\Player\PaellaConfigServiceFactory;
use srag\Plugins\Opencast\Util\Player\PaellaConfigService;
use srag\Plugins\Opencast\Util\Locale\Translator;

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
    public const DEFAULT_WIDTH = 960;
    public const DEFAULT_HEIGHT = 540;
    public const DEFAULT_LIMIT = 10;

    /** @var \ILIAS\DI\Container */
    protected \ILIAS\DI\Container $dic;

    /** @var  ilCtrl */
    protected ilCtrl $ctrl;

    /** @var  ilTabsGUI */
    protected ilTabsGUI $tabs;

    /** @var  ilGlobalTemplateInterface */
    public ilGlobalTemplateInterface $main_tpl;

    /** @var ilTree */
    public ilTree $tree;

    /** @var EventAPIRepository*/
    public EventAPIRepository $event_repository;

    /** @var SeriesRepository */
    public SeriesRepository $series_repository;

    /** @var ilOpenCastPlugin */
    private ilOpenCastPlugin $opencast_plugin;

    /** @var PaellaConfigServiceFactory */
    private PaellaConfigServiceFactory $paellaConfigServiceFactory;

    /** @var PaellaConfigService */
    private PaellaConfigService $paellaConfigService;

    /** @var Translator */
    private Translator $opencast_translator;

    /**
     * @var \ILIAS\HTTP\Services
     */
    private \ILIAS\HTTP\Services $http;

    /**
     * @var bool change_event a flag to determine whether the event change is requested.
     */
    private bool $change_event = false;

    /**
     * Initialisation
     */
    protected function afterConstructor(): void
    {
        global $DIC;
        $this->dic = $DIC;
        $this->http = $DIC->http();
        $this->ctrl = $DIC->ctrl();
        $this->tabs = $DIC->tabs();
        $this->tree = $DIC->repositoryTree();
        $this->main_tpl = $DIC->ui()->mainTemplate();
        $opencast_dic = Init::init();

        $this->opencast_plugin = $opencast_dic[ilOpenCastPlugin::class];
        $this->event_repository = $opencast_dic[EventAPIRepository::class];
        $this->series_repository = $opencast_dic[SeriesAPIRepository::class];
        $this->opencast_translator = $opencast_dic->translator();

        $this->paellaConfigServiceFactory = $opencast_dic->legacy()->paella_config_service_factory();
        $this->paellaConfigService = $this->paellaConfigServiceFactory->get();

        $this->ref_id = $this->retrieveQueryParam(
            'ref_id',
            $this->dic->refinery()->kindlyTo()->int(),
            null
        );

        $this->change_event = $this->retrieveQueryParam(
            'change_event',
            $this->dic->refinery()->kindlyTo()->bool(),
            false
        );

        $this->main_tpl->addJavaScript($this->getPlugin()->getResourcesPath() . '/js/opencastEvent/dist/index.js');

        $this->main_tpl->setPermanentLink($this->getType(), $this->ref_id);
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
     *
     * @param string $cmd Command
     */
    public function performCommand(string $cmd): void
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
            $this->main_tpl->setAlertProperties([
                [
                    "alert" => true,
                    "property" => $this->lng->txt("status"),
                    "value" => $this->lng->txt("offline"),
                ]
            ]);
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
    protected function initCreationForms(string $a_new_type): array
    {
        // To prevent using it out of course or groups.
        if (!$this->checkParentGroupCourse()) {
            $this->main_tpl->setOnScreenMessage('failure', $this->txt("msg_creation_failed"), true);
            $this->ctrl->redirectByClass('ilDashboardGUI', '');
        }

        $forms = [
            self::CFORM_NEW => $this->initCreateForm($a_new_type),
        ];

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
            $this->tabs->addTab(
                'event_settings',
                $this->txt('event_settings'),
                $this->ctrl->getLinkTarget($this, 'editEvent')
            );
        }

        // Make sure the info tab is not there when accessing anonymously.
        if (!ilObjOpencastEventAccess::isAnonymousUser()) {
            $this->addInfoTab();
        }

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
                $this->main_tpl->setOnScreenMessage('success', $this->txt("create_successful"), true);

                $args = func_get_args();
                if ($args) {
                    $this->afterSave($newEventObj, $args);
                } else {
                    $this->afterSave($newEventObj);
                }
                return;
            } else {
                $this->main_tpl->setOnScreenMessage('failure', $this->txt("msg_creation_failed"));
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

        $listing = new OpencastEventListing($this, $form, $this->ref_id, true);
        $this->main_tpl->addCss($this->getPlugin()->getResourcesPath() . '/templates/css/list.min.css');
        $this->main_tpl->addOnLoadCode('il.OpencastEvent.list.init();');
        $this->main_tpl->setContent($listing->render());
    }

    /**
     * This method is used to display a custom event object edit form.
     */
    protected function editEvent(): void
    {
        $this->tabs->activateTab('event_settings');
        $form = $this->initEventForm(false);
        $this->addValuesToForm($form);

        $listing = new OpencastEventListing($this, $form, $this->ref_id, false);
        $this->main_tpl->addJavaScript(
            $this->getPlugin()->getResourcesPath() . '/js/opencastEvent/external-libs/ion.rangeSlider.min.js'
        );
        $this->main_tpl->addCss(
            $this->getPlugin()->getResourcesPath() . '/js/opencastEvent/external-libs/ion.rangeSlider.min.css'
        );
        $this->main_tpl->addCss($this->getPlugin()->getResourcesPath() . '/templates/css/form.min.css');
        $cons_prop_text = $this->lng->txt('cont_constrain_proportions', 'content');
        $slider_config = json_encode($this->getRangeSliderConfig());
        $this->main_tpl->addOnLoadCode('il.OpencastEvent.form.initSettingsForm(' .
            self::DEFAULT_WIDTH * 2 . ', "' . $cons_prop_text . '", ' . $slider_config .
        ');');
        $this->main_tpl->addCss($this->getPlugin()->getResourcesPath() . '/templates/css/list.min.css');
        $this->main_tpl->addOnLoadCode('il.OpencastEvent.list.init();');
        $this->main_tpl->setContent($listing->render());
    }

    /**
     * This method is used to update the event object, and redirects back to editEvent action.
     */
    protected function updateEvent(): void
    {
        $form = $this->initEventForm(false);
        if ($this->checkInput($form)) {
            $this->updateOpencastEventObject($form);
            $this->main_tpl->setOnScreenMessage('success', $this->txt("update_successful"), true);
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
        /** @disregard P1013 The method "getEventId" exists in ilObjOpencastEvent::getEventId */
        $event_id = $this->object->getEventId();
        $event = $this->getEvent($event_id);
        if (!empty($event)) {
            $this->main_tpl->addCss($this->getPlugin()->getResourcesPath() . '/templates/css/player.min.css');
            $this->main_tpl->addOnLoadCode('il.OpencastEvent.player.init(' .
                json_encode($this->getPlayerJSConfig($event)) .
            ');');
            $stream_url = $this->ctrl->getLinkTarget($this, 'streamVideo');
            /** @disregard P1013 The method "getNewTab" exists in ilObjOpencastEvent::getNewTab */
            $tpl_name = $this->object->getNewTab() ? 'tpl.OpencastEventPlayer.html' : 'tpl.OpencastEventPlayerEmbed.html';
            $tpl = new ilTemplate($this->getPlugin()->getDirectory() . '/templates/default/' . $tpl_name, true, true);
            /** @disregard P1013 The method "getNewTab" exists in ilObjOpencastEvent::getNewTab */
            if ($this->object->getNewTab()) {
                $tpl->setVariable('VIDEO_LINK', $stream_url);
                $tpl->setVariable('THUMBNAIL_URL', $event->publications()->getThumbnailUrl());
                $tpl->setVariable('OVERLAY_ICON_URL', $this->getPlugin()->getResourcesPath() . '/templates/images/play.svg');
            } else {
                $tpl->setVariable('URL', $stream_url);
            }
            $content_html = $tpl->get();
        }

        $this->main_tpl->setContent($content_html);
    }

    /**
     * This method is meant to call by the iframe in order to provide the Paella player source code.
     */
    public function streamVideo(): void
    {
        /** @disregard P1013 The method "getEventId" exists in ilObjOpencastEvent::getEventId */
        $event_id = $this->object->getEventId();
        $event = $this->getEvent($event_id);
        if (empty($event)) {
            echo "Error: Event not found";
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
            $this->main_tpl->setOnScreenMessage('failure', $e->getMessage());
            echo $e->getMessage();
            exit;
        }

        $paella_player_tpl = $this->opencast_plugin->getTemplate('paella_player.html', true, true);

        // The Opencast Plugin versions > 5.3.0 has a new way of providing js.
        $main_opencast_js_path = $this->opencast_plugin->getDirectory() . '/js/opencast/dist/index.js';
        $new_paella_player = true;
        if (file_exists($main_opencast_js_path)) {
            $jquery_path = str_replace('.min', '', iljQueryUtil::getLocaljQueryPath());
            $ilias_basic_js_path = 'assets/js/Basic.js';
            $paella_player_tpl->setVariable("JQUERY_PATH", $jquery_path);
            $paella_player_tpl->setVariable("ILIAS_BASIC_JS_PATH", $ilias_basic_js_path);
        } else {
            $new_paella_player = false;
            $paella_player_tpl->setVariable('PAELLA_PLAYER_FOLDER', $this->opencast_plugin->getDirectory()
                . '/node_modules/paellaplayer/build/player');
        }
        $paella_player_tpl->setVariable('TITLE', $event->getTitle());
        $paella_player_tpl->setVariable('DATA', json_encode($data));
        $paella_player_tpl->setVariable('JS_CONFIG', json_encode($this->buildJSConfig($event, $new_paella_player)));

        if ($event->isLiveEvent()) {
            $paella_player_tpl->setVariable('LIVE_WAITING_TEXT', $this->opencast_translator->translate(
                'live_waiting_text',
                'event',
                [date('H:i', $event->getScheduling()->getStart()->getTimestamp())]
            ));
            $paella_player_tpl->setVariable('LIVE_INTERRUPTED_TEXT', $this->opencast_translator->translate('live_interrupted_text', 'event'));
            $paella_player_tpl->setVariable('LIVE_OVER_TEXT', $this->opencast_translator->translate('live_over_text', 'event'));
        }

        $paella_player_tpl->setVariable(
            'STYLE_SHEET_LOCATION',
            ILIAS_HTTP_PATH . '/' .
                strstr($this->opencast_plugin->getDirectory(), 'Customizing/') . '/templates/default/player.css'
        );
        setcookie('lastProfile', "", -1);
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
     * @param bool $new_paella_player if the paella player is new
     *
     * @return stdClass $js_config
     */
    protected function buildJSConfig(Event $event, bool $new_paella_player = true): stdClass
    {
        $js_config = new stdClass();
        $paella_config = [];
        if ($new_paella_player) {
            $paella_config = $this->paellaConfigService->getEffectivePaellaPlayerUrl();
            $js_config->paella_config_livestream_type = PluginConfig::getConfig(PluginConfig::F_LIVESTREAM_TYPE) ?? 'hls';
            $js_config->paella_config_livestream_buffered =
                PluginConfig::getConfig(PluginConfig::F_LIVESTREAM_BUFFERED) ?? false;
            $js_config->paella_config_resources_path = PluginConfig::PAELLA_RESOURCES_PATH;
            $js_config->paella_config_fallback_captions =
                PluginConfig::getConfig(PluginConfig::F_PAELLA_FALLBACK_CAPTIONS) ?? [];
            $js_config->paella_config_fallback_langs = PluginConfig::getConfig(PluginConfig::F_PAELLA_FALLBACK_LANGS) ?? [];

            $paella_themes = $this->paellaConfigService->getPaellaPlayerThemeUrl($event->isLiveEvent());
            $js_config->paella_theme = $paella_themes['theme_url'];
            $js_config->paella_theme_live = $paella_themes['theme_live_url'];
            $js_config->paella_theme_info = $paella_themes['info'];

            $js_config->paella_preview_fallback = $this->paellaConfigService->getPaellaPlayerPreviewFallback();
        } else {
            $paella_config = $this->paellaConfigService->getEffectivePaellaPlayerUrl($event->isLiveEvent());
            $js_config->paella_player_folder = $this->opencast_plugin->getDirectory() . '/node_modules/paellaplayer/build/player';
        }

        $js_config->paella_config_file = $paella_config['url'];
        $js_config->paella_config_info = $paella_config['info'];
        $js_config->paella_config_is_warning = $paella_config['warn'];

        if ($event->isLiveEvent()) {
            // script to check live stream availability.
            $js_config->check_script_hls = $this->opencast_plugin->getDirectory() . '/src/Util/check_hls_status.php';
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
        /** @disregard P1013 The method "getMaximize" exists in ilObjOpencastEvent::getMaximize */
        $js_config->maximize = $this->object->getMaximize();
        /** @disregard P1013 The method "getWidth" exists in ilObjOpencastEvent::getWidth */
        $js_config->width = $this->object->getWidth() ? $this->object->getWidth() : self::DEFAULT_WIDTH;
        /** @disregard P1013 The method "getHeight" exists in ilObjOpencastEvent::getHeight */
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
    private function createOpencastEventObject(ilPropertyFormGUI $form): ?ilObjOpencastEvent
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
    private function updateOpencastEventObject(ilPropertyFormGUI $form): void
    {
        $event = $this->getEvent($form->getInput('event_id'));
        if (empty($event)) {
            return;
        }
        $this->object->setTitle($event->getTitle());
        $this->object->setDescription($event->getDescription());
        /** @disregard P1013 The method "setOnline" exists in ilObjOpencastEvent::setOnline */
        $this->object->setOnline($form->getInput('online') ? true : false);
        /** @disregard P1013 The method "setEventId" exists in ilObjOpencastEvent::setEventId */
        $this->object->setEventId($form->getInput('event_id'));
        /** @disregard P1013 The method "setNewTab" exists in ilObjOpencastEvent::setNewTab */
        $this->object->setNewTab($form->getInput('new_tab') ? true : false);
        /** @disregard P1013 The method "setMaximize" exists in ilObjOpencastEvent::setMaximize */
        $this->object->setMaximize($form->getInput('size_type') == 'maximize' ? true : false);
        if ($form->getInput('size_type') == 'custom') {
            $embed_size = $form->getInput('embed_size');
            /** @disregard P1013 The method "setWidth" exists in ilObjOpencastEvent::setWidth */
            $this->object->setWidth(intval($embed_size['width'], 10));
            /** @disregard P1013 The method "setHeight" exists in ilObjOpencastEvent::setHeight */
            $this->object->setHeight(intval($embed_size['height'], 10));
        }
        $this->object->update();
    }

    /**
     * Helper function to custom checks the form input.
     *
     * @param ilPropertyFormGUI $form
     *
     * @return bool based on the checkers defined, returns true of false.
     */
    protected function checkInput(ilPropertyFormGUI $form): bool
    {
        $return = $form->checkInput();

        // We need event_id in any case!
        $event_id = $form->getInput('event_id');
        if (empty($event_id)) {
            $this->main_tpl->setOnScreenMessage('failure', $this->txt("no_event_id"), true);
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
    protected function initEventForm(bool $is_new = true): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();

        if ($is_new) {
            $form->setTitle($this->txt('obj_' . $this->getType()));
            $form->setId($this->getType() . '_event_new');
            $form->setForceTopButtons(true);

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
            $preview_image = new ilNonEditableValueGUI($this->opencast_translator->translate('event_preview'), '', true);
            /** @disregard P1013 The method "setWidth" exists in ilObjOpencastEvent::setWidth */
            $preview_image_width = $this->object->getWidth() ? $this->object->getWidth() : self::DEFAULT_WIDTH;
            /** @disregard P1013 The method "getHeight" exists in ilObjOpencastEvent::getHeight */
            $preview_image_height = $this->object->getHeight() ? $this->object->getHeight() : self::DEFAULT_HEIGHT;
            $preview_image_tpl = new ilTemplate($this->getPlugin()->getDirectory() . '/templates/default/tpl.OpencastEventPreviewImage.html', false, false);
            $preview_image_tpl->setVariable('DYNAMIC_WIDTH', $preview_image_width);
            $preview_image_tpl->setVariable('DYNAMIC_HEIGHT', $preview_image_height);
            /** @disregard P1013 The method "getEventId" exists in ilObjOpencastEvent::getEventId */
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
            $slider_tpl = new ilTemplate($this->getPlugin()->getDirectory() . '/templates/default/tpl.OpencastEventInputSlider.html', false, false);
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
     * Gets the config for range slider
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
            'step' => 5,
            'grid' => true,
            'postfix' => '%',
        ];
    }

    /**
     * Helper function to add values to the edit form.
     *
     * @param $form ilPropertyFormGUI
     */
    protected function addValuesToForm(ilPropertyFormGUI &$form): void
    {
        /** @disregard P1013 The methods exist in ilObjOpencastEvent class */
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
        /** @disregard P1013 The method "getMaximize" exists in ilObjOpencastEvent::getMaximize */
        $size_type = $this->object->getMaximize() ? 'maximize' : 'custom';
        $values_array['size_type'] = $size_type;
        if (isset($this->change_event) && $this->change_event) {
            $values_array['change_event'] = true;
        }
        $form->setValuesByArray($values_array);
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
     * @return bool
     */
    private function checkParentGroupCourse(): bool
    {
        $is_checked = false;
        if (isset($this->ref_id) && $this->tree->checkForParentType($this->ref_id, 'grp') > 0 ||
            $this->tree->checkForParentType($this->ref_id, 'crs') > 0) {
            $is_checked = true;
        }
        return $is_checked;
    }

    /**
     * Gets the events available to user to fill the listing.
     *
     * @param array $filter Filter array
     * @param string $sort the sort string
     * @param int $offset Offset num
     * @param int $limit Limit num
     * @param int $extra_limit additional num to the limit
     *
     * @return array $events events list
     */
    public function getEvents(
        array $filter = [],
        string $sort = '',
        int $offset = 0,
        int $limit = 1000,
        int $extra_limit = 1
    ): array {
        $events = [];
        try {
            $common_idp = PluginConfig::getConfig(PluginConfig::F_COMMON_IDP);
            $events = $this->event_repository->getFiltered(
                $filter,
                $common_idp ? xoctUser::getInstance($this->dic->user())->getIdentifier() : '',
                $common_idp ? [] : [xoctUser::getInstance($this->dic->user())->getUserRoleName()],
                $offset,
                $limit + $extra_limit,
                $sort,
                true
            );
        } catch (Exception $e) {
            if ($e->getCode() !== 403) {
                $this->main_tpl->setOnScreenMessage('failure', $e->getMessage());
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
    public function getEvent(string $event_id): ?Event
    {
        $event = null;
        try {
            $event = $this->event_repository->find($event_id);
        } catch (Exception $e) {
            $this->main_tpl->setOnScreenMessage('failure', $e->getMessage());
        }
        return $event;
    }

    /**
     * Gets the series available to user to use as filter option.
     *
     * @return array $series_options series option list
     */
    public function getSeriesFilterOptions(): array
    {
        $series_options = [];
        $xoctUser = xoctUser::getInstance($this->dic->user());
        try {
            $this->series_repository->getOwnSeries($xoctUser);
            foreach ($this->series_repository->getAllForUser($xoctUser->getUserRoleName()) as $series) {
                $series_options[$series->getIdentifier()] =
                    $series->getMetadata()->getField(MDFieldDefinition::F_TITLE)->getValue()
                    . ' (...' . substr($series->getIdentifier(), -4, 4) . ')';
            }

            natcasesort($series_options);
        } catch (Exception $th) {
            $series_options = [];
        }

        return $series_options;
    }


    /**
     * Retrieve and transform a query parameter value.
     *
     * This helper method checks for the existence of the query parameter
     * and applies an ILIAS refinery transformation. If the parameter is
     * missing or the transformation returns a falsy value, the provided
     * default is returned.
     *
     * @param string $key Parameter name to retrieve from query string.
     * @param \ILIAS\Refinery\Transformation $transformation Transformation instance to apply.
     * @param mixed $default Default value to return when parameter is absent or empty.
     *
     * @return mixed Transformed parameter value or default.
     */
    private function retrieveQueryParam(
        string $key,
        \ILIAS\Refinery\Transformation $transformation,
        mixed $default = null
    ): mixed {
        if (
            $this->http->wrapper()->query()->has($key) &&
            $this->http->wrapper()->query()->retrieve(
                $key,
                $transformation
            )
        ) {
            return $this->http->wrapper()->query()->retrieve(
                $key,
                $transformation
            );
        }
        return $default;
    }
}
