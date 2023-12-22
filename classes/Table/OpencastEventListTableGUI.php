<?php
use srag\Plugins\Opencast\Model\User\xoctUser;
use srag\Plugins\Opencast\Model\Metadata\Definition\MDFieldDefinition;
use srag\Plugins\Opencast\DI\OpencastDIC;
use srag\Plugins\Opencast\Model\Config\PluginConfig;
use srag\Plugins\Opencast\Model\Event\Event;

use srag\Plugins\Opencast\Model\Series\SeriesRepository;
use srag\Plugins\Opencast\Model\Series\SeriesAPIRepository;

/**
 * OpencastEventListTableGUI class for event selection
 *
 * @extends ilTable2GUI
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 */
class OpencastEventListTableGUI extends ilTable2GUI
{
    public const F_TEXTFILTER = 'textFilter';
    public const F_SERIES = 'series';
    public const F_START_FROM = 'start_from';
    public const F_START_TO = 'start_to';
    public const F_START = 'start';

    /**
     * @var ilObjOpencastEventGUI
     */
    protected $parent_obj;
    /**
     * @var Container
     */
    protected $dic;
    /**
     * @var ilOpenCastPlugin
     */
    private $opencast_plugin;
    /**
     * @var ilOpencastEventPlugin
     */
    protected $plugin;
    /**
     * @var SeriesRepository
     */
    private $series_repository;
    /**
     * @var array
     */
    protected $filter = [];
    /**
     * @var int
     */
    protected $ref_id = 0;

    /**
    * Constructor
    */
    public function __construct($a_parent_obj, $a_parent_cmd, $ref_id = 0)
    {
        global $DIC, $opencastContainer;

        $this->dic = $DIC;
        $this->parent_obj = $a_parent_obj;
        $this->plugin = $a_parent_obj->getPlugin();
        $this->opencast_plugin = ilOpenCastPlugin::getInstance();
        $opencast_dic = OpencastDIC::getInstance();

        if (method_exists($opencast_dic, 'series_repository')) {
            $this->series_repository = $opencast_dic->series_repository();
        } else if (!empty($opencastContainer)) {
            $this->series_repository = $opencastContainer->get(SeriesAPIRepository::class);
        }

        PluginConfig::setApiSettings();
        $this->setRefId($ref_id);
        $this->setId($this->parent_obj->getType() . '_event_table_' . $this->dic->user()->getId() . '_' . $this->getRefId());

        parent::__construct($a_parent_obj, $a_parent_cmd);
        $this->setTitle($this->plugin->txt('table_title') . ' <span class="asterisk">*</span>');
        $this->initFilter();

        $this->setExternalSegmentation(true);
        $this->setExternalSorting(true);
        $this->determineOffsetAndOrder();
        $this->setEnableNumInfo(false);
        $this->setShowRowsSelector(false);
        $this->setEnableHeader(true);
        $this->setEnableTitle(true);
        $this->setDefaultFilterVisiblity(true);

        $this->addColumn('', '', 1);
        foreach ($this->getEventColumns() as $column_name => $column_txt) {
            $avialable_sorts = ['title', 'location', 'series', 'start'];
            $sort = '';
            $class = '';
            if (in_array($column_name, $avialable_sorts)) {
                $sort = $column_name;
                $class = 'sortable-th ' . $column_name;
            }
            $this->addColumn($column_txt, $sort, '', false, $class);
        }
        $this->setRowTemplate($this->plugin->getDirectory() . '/templates/html/tpl.OpencastEventTabeFull.html');

        $this->setFormAction($this->dic->ctrl()->getFormAction($a_parent_obj));
    }

    /**
     * @inheritdoc
     *
     * @param int $ref_id
     * @return OpencastEventListTableGUI
     */
    public function setRefId($ref_id): OpencastEventListTableGUI
    {
        $this->ref_id = $ref_id;
        return $this;
    }

    /**
     * @inheritdoc
     *
     * @return int
     */
    public function getRefId(): int
    {
        return $this->ref_id;
    }

    /**
     * @inheritdoc
     */
    protected function fillRow($row): void
    {
        if (!isset($row['object'])) {
            $this->tpl->setVariable("TXT_EMPTY_INFO", $row);
        } else {

            /** @var Event $object */
            $object = $row['object'];

            $row_id = 'xoce_tr_' . $row['identifier'];
            $selectable = $object->getProcessingState() == Event::STATE_SUCCEEDED;
            $row_tooltip_txt = $selectable ? $this->plugin->txt('table_row_tooltip_txt') : $this->plugin->txt('table_row_tooltip_txt_not_selectable');
            ilTooltipGUI::addTooltip($row_id, $row_tooltip_txt);

            $this->tpl->setVariable('ROW_ID', $row_id);
            $this->tpl->setVariable('SELECTABLE', $selectable);
            $this->tpl->setVariable('DATA_EVENT_ID', $row['identifier']);
            $this->tpl->setVariable('DATA_TITLE', $row['title']);
            $this->tpl->setVariable('DATA_DESCRIPTION', $row['description']);

            $this->tpl->setVariable('UNSELECTED_ICON', $this->plugin->getDirectory() . '/templates/images/circle.svg');
            $this->tpl->setVariable('SELECTED_ICON', $this->plugin->getDirectory() . '/templates/images/circle-dot-filled.svg');

            foreach ($this->getEventColumns() as $column_name => $column_txt) {
                $column = $this->getColumnValue($column_name, $row);
                $temp_identifier = strtoupper($column_name);
                if (!empty($column)) {
                    $this->tpl->setVariable($temp_identifier, $column);
                } else {
                    $this->tpl->setVariable($temp_identifier, " ");
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function initFilter(): void
    {
        $title = $this->addFilterItemByMetaType(self::F_TEXTFILTER, self::FILTER_TEXT, false, $this->plugin->txt(self::F_TEXTFILTER));
        $title->readFromSession();
        $this->filter[self::F_TEXTFILTER] = $title->getValue();

        $series = $this->addFilterItemByMetaType(self::F_SERIES, self::FILTER_SELECT, false, $this->opencast_plugin->txt('event_series'));
        $series->setOptions($this->getSeriesFilterOptions());
        $series->readFromSession();
        $this->filter[self::F_SERIES] = $series->getValue();

        $start = $this->addFilterItemByMetaType(self::F_START, self::FILTER_DATE_RANGE, false, $this->opencast_plugin->txt('event_start'));
        $start->readFromSession();
        $this->filter[self::F_START_FROM] = $start->getValue()['from'];
        $this->filter[self::F_START_TO] = $start->getValue()['to'];
    }

    /**
     * @inheritdoc
     */
    public function getSelectableColumns(): array
    {
        return [];
    }


    //////////////////////
    // Helper functions //
    //////////////////////

    /**
     * Gets the series available to user to use as filter option.
     *
     * @return array $series_options series option list
     */
    private function getSeriesFilterOptions(): array
    {
        $series_options = ['' => '-'];
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
     * Creates an array of filtering items to be consumed by the opencast event repository
     *
     * @return array $filter list of filters
     */
    public function buildFilterArray(): array
    {
        $filter = ['status' => 'EVENTS.EVENTS.STATUS.PROCESSED'];

        if ($title_filter = $this->filter[self::F_TEXTFILTER]) {
            $filter[self::F_TEXTFILTER] = $title_filter;
        }

        if ($series_filter = $this->filter[self::F_SERIES]) {
            $filter[self::F_SERIES] = $series_filter;
        }

        /** @var $start_filter_from ilDateTime */
        /** @var $start_filter_to ilDateTime */
        $start_filter_from = $this->filter[self::F_START_FROM];
        $start_filter_to = $this->filter[self::F_START_TO];
        if ($start_filter_from || $start_filter_to) {
            $filter['start'] = ($start_filter_from ? $start_filter_from->get(IL_CAL_FKT_DATE, 'Y-m-d\TH:i:s') : '1970-01-01T00:00:00')
                . '/' . ($start_filter_to ? $start_filter_to->get(IL_CAL_FKT_DATE, 'Y-m-d\T23:59:59') : '2200-01-01T00:00:00');
        }

        return $filter;
    }

    /**
     * Gets the value of the table column
     *
     * @param string $column the id of the column
     * @param array $row the row data.
     *
     * @return string the column value
     */
    private function getColumnValue(string $column, array $row): string
    {
        switch ($column) {
            case 'thumbnail':
                /** @var Event $object */
                $object = $row['object'];
                $thumbnail_img_tpl = new ilTemplate($this->plugin->getDirectory() . '/templates/html/tpl.OpencastEventTableThumbnail.html', false, false);
                $thumbnail_img_tpl->setVariable('SRC', $object->publications()->getThumbnailUrl());
                return $thumbnail_img_tpl->get();
            case 'title':
                /** @var Event $object */
                $object = $row['object'];
                $renderer = new xoctEventRenderer($object);
                return $row['title'] . $renderer->getStateHTML();
            case 'series':
                /** @var Event $object */
                $object = $row['object'];
                $series_title = $this->series_repository->find($object->getSeries())->getMetadata()->getField('title')->getValue();
                return $series_title . ' (' . $object->getSeries() . ')';
            case 'start':
                $start_timestamp = !empty($row['start_unix']) ? $row['start_unix'] : strtotime($row['startDate']);
                return date('d.m.Y H:i', $start_timestamp);
            case 'location':
                return $row['location'];
        }
    }

    /**
     * Returns a list of columns defining their id and text
     *
     * @return array columns list
     */
    private function getEventColumns(): array
    {
        return [
            'thumbnail' => $this->opencast_plugin->txt('event_preview'),
            'title' => $this->opencast_plugin->txt('event_title'),
            'series' => $this->opencast_plugin->txt('event_series'),
            'start' => $this->opencast_plugin->txt('event_start'),
            'location' => $this->opencast_plugin->txt('event_location'),
        ];
    }
}
