<?php
/**
 * ilOpencastEventConfigGUI
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @extends ilPluginConfigGUI
 */
class ilOpencastEventConfigGUI extends ilPluginConfigGUI
{
    /**
     * @var elanev\OpencastEvent\Config\PluginConfig
     */
    protected $config_object;
    /**
     * @var \ilCtrlInterface
     */
    private $ctrl;
    /**
     * @var \ilGlobalTemplateInterface
     */
    private $main_tpl;
    /**
     * @var \ilLanguage
     */
    private $language;
    /**
     * @var \ilTabsGUI
     */
    private $tabs;

    public function __construct()
    {
        global $DIC;
        $this->ctrl = $DIC->ctrl();
        $this->main_tpl = $DIC->ui()->mainTemplate();
        $this->language = $DIC->language();
        $this->tabs = $DIC->tabs();
        $this->config_object = new \elanev\OpencastEvent\Config\PluginConfig();
    }


    /**
     * @return array
     */
    public function getFields()
    {
        return [
            $this->config_object::F_THUMBNAIL_LINK => [
                'type' => 'ilCheckboxInputGUI',
                'hasInfo' => true,
                'subItems' => [],
                'value' => (bool) $this->config_object::getConfig($this->config_object::F_THUMBNAIL_LINK) ?? false
            ]
        ];
    }

    public function executeCommand(): void
    {
        parent::executeCommand();
    }

    public function performCommand($cmd): void
    {
        switch ($cmd) {
            case "configure":
            case "save":
                $this->$cmd();
                break;
        }
    }

    /**
     * Configure screen
     */
    public function configure(): void
    {
        $this->initConfigurationForm();
        $this->fillForm();
        $this->main_tpl->setContent($this->form->getHTML());
    }

    /**
     * Set form item values
     */
    private function fillForm(): void
    {
        $values = [];
        foreach ($this->getFields() as $key => $item) {
            if (isset($item['value'])) {
                $values[$key] = $item['value'];
            }
        }
        $this->form->setValuesByArray($values);
    }


    /**
     * @return ilPropertyFormGUI
     */
    public function initConfigurationForm(): \ilPropertyFormGUI
    {
        $this->form = new ilPropertyFormGUI();

        foreach ($this->getFields() as $key => $item) {
            $field = new $item['type']($this->txt($key), $key);
            if (!empty($item['hasInfo'])) {
                $field->setInfo($this->txt($key . '_info'));
            }
            $this->form->addItem($field);
        }

        $this->form->addCommandButton("save", $this->language->txt("save"));

        $this->form->setTitle($this->txt("header"));
        $this->form->setFormAction($this->ctrl->getFormAction($this));

        return $this->form;
    }

    /**
     * Saves the config.
     */
    public function save(): void
    {
        $this->initConfigurationForm();
        if ($this->form->checkInput()) {
            foreach ($this->getFields() as $key => $item) {
                $form_value = $this->form->getInput($key) ?? null;
                if (!is_null($form_value)) {
                    $this->config_object::setConfig($key, $form_value);
                }
                if (isset($item['subItems']) && !empty($item['subItems'])) {
                    foreach ($item['subItems'] as $subkey => $subitem) {
                        $subitem_form_value = $this->form->getInput($key . "_" . $subkey) ?? null;
                        if (!is_null($subitem_form_value)) {
                            $this->config_object::setConfig($key . "_" . $subkey, $subitem_form_value);
                        }
                    }
                }
            }
            ilUtil::sendSuccess(
                $this->txt("saved"),
                true
            );
            $this->ctrl->redirect($this, "configure");
        } else {
            $this->form->setValuesByPost();
            $this->main_tpl->setContent($this->form->getHtml());
        }
    }

    /**
     * @param string $key the key lang string
     * @return string
     */
    public function txt($key): string
    {
        return $this->plugin_object->txt('config_' . $key);
    }
}
