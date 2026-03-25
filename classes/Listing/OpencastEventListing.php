<?php

declare(strict_types=1);
namespace elanev\OpencastEvent\Listing;

use ilOpencastEventPlugin;
use ilOpenCastPlugin;
use ilTemplate;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ILIAS\UI\Component\Input\Container\Filter\Standard as StandardFilter;
use ilPropertyFormGUI;
use srag\Plugins\Opencast\Container\Init;
use srag\Plugins\Opencast\Model\Event\Event;
use srag\Plugins\Opencast\Util\Locale\Translator;

/**
 * OpencastEventListing class for event selection listing in opencast event object.
 *
 * It handles both ways of providing the listing;
 * 1. The listing within a form for new insertion.
 * 2. The listing just right after the form for edit.
 *
 * @author Farbod Zamani Boroujeni <zamani@elan-ev.de>
 * @version 1.0 für ILIAS 10
 */
class OpencastEventListing
{
    /** @var string the filter flag for title text */
    public const F_TEXTFILTER = 'textFilter';

    /** @var string the filter flag for series */
    public const F_SERIES = 'series';

    /** @var string the filter flag for start date from */
    public const F_START_FROM = 'start_from';

    /** @var string the filter flag for start date to */
    public const F_START_TO = 'start_to';

    /** @var string the filter flag for start date */
    public const F_START = 'start';

    /** @var string the sorting flag for title ascending */
    public const F_SORT_TITLE_ASC = 'title_asc';

    /** @var string the sorting flag for title descending */
    public const F_SORT_TITLE_DESC = 'title_desc';

    /** @var string the sorting flag for series ascending */
    public const F_SORT_SERIES_ASC = 'series_name_asc';

    /** @var string the sorting flag for series descending */
    public const F_SORT_SERIES_DESC = 'series_name_desc';

    /** @var string the sorting flag for start date ascending */
    public const F_SORT_START_ASC = 'start_date_asc';

    /** @var string the sorting flag for start date descending */
    public const F_SORT_START_DESC = 'start_date_desc';

    /** @var string the sorting flag for location ascending */
    public const F_SORT_LOCATION_ASC = 'location_asc';

    /** @var string the sorting flag for location descending */
    public const F_SORT_LOCATION_DESC = 'location_desc';

    /** @var string the query param flag for sorting */
    public const F_SORT_QUERY_PARAM = 'sort';

    /** @var string the query param flag for pagination */
    public const F_PAGINATION_QUERY_PARAM = 'page';

    /** @var int the default constant for item per page */
    public const F_PAGINATION_PER_PAGE = 10;

    /** @var int the default constant for API limit of events */
    public const F_PAGINATION_API_LIMIT = 1000;

    /**
     * @var \ILIAS\DI\Container
     */
    protected \ILIAS\DI\Container $dic;
    /**
     * @var Factory
     */
    private Factory $ui_factory;
    /**
     * @var Renderer
     */
    private Renderer $renderer;
    /**
     * @var ilOpencastEventPlugin
     */
    protected ilOpencastEventPlugin $plugin;
    /**
     * @var ilOpenCastPlugin
     */
    private ilOpenCastPlugin $opencast_plugin;

    /** @var string the filter id */
    public string $filter_id;

    /** @var Translator */
    private Translator $opencast_translator;


    function __construct(
        protected \ilObjOpencastEventGUI $gui,
        protected ilPropertyFormGUI $form,
        private int $ref_id = 0,
        private bool $is_new = true
    ) {
        global $DIC;
        $this->dic = $DIC;
        $this->ui_factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->plugin = $this->gui->getPlugin();
        $opencast_dic = Init::init();
        $this->opencast_plugin = $opencast_dic[ilOpenCastPlugin::class];
        $this->opencast_translator = $opencast_dic->translator();
        $this->filter_id = $this->gui::class . '_filter_' . $ref_id;
    }

    /**
     * Build and return the standard UI filter component for event listing.
     *
     * Includes fields for text search, series selection, and start date range.
     * The filter is bound to the current listing action link.
     *
     * @return \ILIAS\UI\Component\Input\Container\Filter\Standard
     */
    protected function getListingFilter(): StandardFilter
    {
        $filter_inputs = [
            self::F_TEXTFILTER  => $this->ui_factory->input()->field()
                                        ->text($this->plugin->txt(self::F_TEXTFILTER)),
            self::F_SERIES      => $this->ui_factory->input()->field()
                                        ->select(
                                            $this->opencast_translator->translate('event_series'),
                                            $this->gui->getSeriesFilterOptions()
                                        ),
            self::F_START       => $this->ui_factory->input()->field()
                                        ->duration($this->opencast_translator->translate('event_start'))
                                        ->withUseTime(false),
        ];

        $action = $this->getActionLink('filter');

        $filter = $this->dic->uiService()->filter()->standard(
            $this->filter_id,
            $action,
            $filter_inputs,
            [true, true, true],
            true,
            true
        );

        return $filter;
    }

    /**
     * Extract selected filter values from a standard filter component.
     *
     * @param \ILIAS\UI\Component\Input\Container\Filter\Standard $filter
     * @return array|null Filter values as associative array or null when no data.
     */
    protected function getListingFilterData(StandardFilter $filter): ?array
    {
        return $this->dic->uiService()->filter()->getData($filter);
    }

    /**
     * Render the filter component into HTML.
     *
     * @param \ILIAS\UI\Component\Input\Container\Filter\Standard $filter
     * @return string HTML output for the filter bar.
     */
    protected function renderListingFilters(StandardFilter $filter): string
    {
        return $this->renderer->render($filter);
    }

    /**
     * Construct and return the sortation control for event listing.
     *
     * Supported sort options:
     * - start date asc/desc
     * - title asc/desc
     * - series asc/desc
     * - location asc/desc
     *
     * @return \ILIAS\UI\Component\ViewControl\Sortation
     */
    protected function getListingSort(): \ILIAS\UI\Component\ViewControl\Sortation
    {
        $sortation = $this->ui_factory->viewControl()->sortation(
            [
                self::F_SORT_START_ASC      => $this->plugin->txt(self::F_SORT_START_ASC),
                self::F_SORT_START_DESC     => $this->plugin->txt(self::F_SORT_START_DESC),
                self::F_SORT_TITLE_ASC      => $this->plugin->txt(self::F_SORT_TITLE_ASC),
                self::F_SORT_TITLE_DESC     => $this->plugin->txt(self::F_SORT_TITLE_DESC),
                self::F_SORT_SERIES_ASC     => $this->plugin->txt(self::F_SORT_SERIES_ASC),
                self::F_SORT_SERIES_DESC    => $this->plugin->txt(self::F_SORT_SERIES_DESC),
                self::F_SORT_LOCATION_ASC   => $this->plugin->txt(self::F_SORT_LOCATION_ASC),
                self::F_SORT_LOCATION_DESC  => $this->plugin->txt(self::F_SORT_LOCATION_DESC),
            ],
            $this->getListingSortValue()
        )->withTargetURL($this->getActionLink('sortation'), self::F_SORT_QUERY_PARAM);

        return $sortation;
    }

    /**
     * Retrieve the active sort key from query parameters.
     *
     * Falls back to default start date descending if no valid sort is present.
     *
     * @return string Sort key value (one of the F_SORT_* constants).
     */
    protected function getListingSortValue(): string
    {
        $selected = self::F_SORT_START_DESC;
        if (
            $this->dic->http()->wrapper()->query()->has(self::F_SORT_QUERY_PARAM) &&
            $this->dic->http()->wrapper()->query()->retrieve(
                self::F_SORT_QUERY_PARAM,
                $this->dic->refinery()->kindlyTo()->string()
            )
        ) {
            $selected = $this->dic->http()->wrapper()->query()->retrieve(
                self::F_SORT_QUERY_PARAM,
                $this->dic->refinery()->kindlyTo()->string()
            );
        }
        return $selected;
    }

    /**
     * Create pagination controls for event listing.
     *
     * @param int $total Total number of items (for pagination calculation).
     * @return \ILIAS\UI\Component\ViewControl\Pagination
     */
    protected function getListingPagination(int $total): \ILIAS\UI\Component\ViewControl\Pagination
    {
        $pagination = $this->ui_factory->viewControl()->pagination()
            ->withTargetURL(
                $this->getActionLink('pagination'), self::F_PAGINATION_QUERY_PARAM
            )
            ->withTotalEntries($total)
            ->withPageSize(self::F_PAGINATION_PER_PAGE)
            ->withMaxPaginationButtons(2)
            ->withCurrentPage($this->getCurrentPage());
        return $pagination;
    }

    /**
     * Read the current page index from query parameters.
     *
     * Defaults to 0 when the page parameter is absent.
     *
     * @return int Current pagination page index.
     */
    protected function getCurrentPage(): int
    {
        $current_page = 0;
        if ($this->dic->http()->wrapper()->query()->has(self::F_PAGINATION_QUERY_PARAM)) {
            $current_page = $this->dic->http()->wrapper()->query()->retrieve(
                self::F_PAGINATION_QUERY_PARAM,
                $this->dic->refinery()->kindlyTo()->int()
            );
        }
        return (int) $current_page;
    }
    /**
     * Calculate the API page offset for event fetch requests.
     *
     * Uses the current page and items-per-page to derive the API page
     * index (based on a fixed API limit constant).
     *
     * @return int Computed API offset page.
     */
    protected function getApiOffset(): int
    {
        $global_offset = $this->getCurrentPage() * self::F_PAGINATION_PER_PAGE;
        $api_page = intdiv($global_offset, self::F_PAGINATION_API_LIMIT);
        return $api_page;
    }

    /**
     * Map Event objects to UI item components for listing items.
     *
     * Each event is rendered with title, description, thumbnail, and
     * metadata properties (date, series, location, identifier, status).
     *
     * @param Event[] $events Event objects to convert
     * @return array Array of UI items or empty when no events
     */
    protected function getListingsItems(array $events): ?array
    {
        $items = [];
        foreach ($events as $event) {

            // Creating an item component.
            $item = $this->ui_factory
                ->item()
                ->standard(
                    $event->getTitle()
                )
                ->withDescription($event->getDescription());

            // Preparing lead image as for the thumbnail.
            $lead_image = $this->ui_factory->image()->responsive(
                $event->publications()->getThumbnailUrl(),
                'src'
            );

            // Preparing the series property with name and id.
            $series_name = $event->getSeries();
            try {
                $series_title = $this->gui->series_repository->find($event->getSeries())
                                    ->getMetadata()->getField('title')->getValue();
                $series_name = $series_title . ' (' . $event->getSeries() . ')';
            } catch (\Exception $e) {
            }

            // Preparing the ID property with extra spans to be used by js functions.
            $span_id_tpl = new ilTemplate(
                $this->plugin->getDirectory() . '/templates/default/tpl.OpencastEventListPropSpanId.html',
                true,
                true
            );
            $span_id_tpl->setCurrentBlock('id');
            $span_id_tpl->setVariable('ID', $event->getIdentifier());
            $span_id_tpl->parseCurrentBlock();

            $span_id_tpl->setCurrentBlock('title');
            $span_id_tpl->setVariable('TITLE', $event->getTitle());
            $span_id_tpl->parseCurrentBlock();

            $span_id_tpl->setCurrentBlock('desc');
            $span_id_tpl->setVariable('DESC', $event->getDescription());
            $span_id_tpl->parseCurrentBlock();

            // Preparing the Status property with extra spans to be used by js functions.
            $selectable = $event->getProcessingState() == Event::STATE_SUCCEEDED;
            $span_status_tpl = new ilTemplate(
                $this->plugin->getDirectory() . '/templates/default/tpl.OpencastEventListPropSpanStatus.html',
                true,
                true
            );
            $span_status_tpl->setCurrentBlock('selectable');
            $span_status_tpl->setVariable('SELECTABLE', $selectable ? 'true' : 'false');
            $span_status_tpl->parseCurrentBlock();

            $span_status_tpl->setCurrentBlock('text');
            $item_status_txt = $selectable ?
                $this->plugin->txt('list_item_status_txt') :
                $this->plugin->txt('list_item_status_txt_not_selectable');
            $span_status_tpl->setVariable('TEXT', $item_status_txt);
            $span_status_tpl->parseCurrentBlock();

            $span_status_tpl->setCurrentBlock('selected');
            $span_status_tpl->setVariable('SELECTED', $this->plugin->txt('list_item_status_selected_txt'));
            $span_status_tpl->parseCurrentBlock();

            /** @disregard P1013 The method "withLeadImage" exists but intelephense cannot find it! */
            $items[] = $item->withProperties([
                $this->opencast_translator->translate("event_date") => $event->getStart()->format('d.m.Y H:i'),
                $this->opencast_translator->translate("event_series") => $series_name,
                $this->opencast_translator->translate("event_location") => $event->getLocation(),
                $this->opencast_translator->translate("event_identifier") => $span_id_tpl->get(),
                $this->plugin->txt("list_status_prop") => $span_status_tpl->get(),
            ])->withLeadImage(
                $lead_image
            );
        }

        return $items;
    }

    /**
     * Render a list panel with sort and pagination controls.
     *
     * @param array $items UI items for the list
     * @param int $total Total number of entries for pagination
     * @return string Rendered HTML of the panel listing items
     */
    protected function renderPanelListing(array $items, int $total): string
    {
        $listing = $this->ui_factory->panel()->listing()->standard(
            $this->plugin->txt('listing_title'),
            [
                $this->ui_factory->item()->group("", $items)
            ]
        )->withViewControls([
            $this->getListingPagination($total),
            $this->getListingSort(),
        ]);

        return $this->renderer->render($listing);
    }

    /**
     * Build and render the full event listing with filter, sort and pagination.
     *
     * - Collects filter input
     * - Applies sorting and pagination
     * - Loads events from repository
     * - Renders list into form-specific template
     *
     * @return string Output HTML for the listing interface
     */
    public function render(): string
    {
        $filter = $this->getListingFilter();
        $filter_data = $this->getListingFilterData($filter);
        $filter_api_array = $this->buildFilterArray($filter_data);
        $sort_value = $this->getListingSortValue();
        $sort_api_string = $this->buildSortString($sort_value);
        $filter_html = $this->renderListingFilters($filter);

        $all_events = $this->gui->getEvents(
            $filter_api_array,
            $sort_api_string,
            $this->getApiOffset(),
            self::F_PAGINATION_API_LIMIT,
            self::F_PAGINATION_PER_PAGE
        );

        $total_current = count($all_events);

        $total = $total_current + ($this->getApiOffset() * self::F_PAGINATION_PER_PAGE);

        $events_chunked = array_chunk($all_events, self::F_PAGINATION_PER_PAGE);
        $current_chunk_index = $this->getCurrentPage();
        if ($this->getApiOffset() > 0) {
            $current_chunk_index = count($events_chunked) - 1;
        }

        $items_paginated_events = $events_chunked[$current_chunk_index];

        $items = $this->getListingsItems($items_paginated_events);

        $listing_html = $this->renderPanelListing($items, $total);

        if ($this->is_new) {
            return $this->renderListingWithingForm($listing_html, $filter_html);
        }

        return $this->renderListingAfterForm($listing_html, $filter_html);
    }

    /**
     * Render the listing layout for edit form context.
     *
     * Inserts rendered filter and listing HTML into the edit form template.
     *
     * @param string $listing_html Rendered listing contents.
     * @param string $filter_html Rendered filter controls HTML.
     * @return string Final rendered HTML for edit view.
     */
    protected function renderListingAfterForm(string $listing_html, string $filter_html): string
    {
        $edit_event_form_tpl = new ilTemplate(
            $this->plugin->getDirectory() . '/templates/default/tpl.OpencastEventEdit.html',
            true,
            true
        );

        $edit_event_form_tpl->setCurrentBlock('form');
        $edit_event_form_tpl->setVariable('FORM', $this->form->getHTML());
        $edit_event_form_tpl->parseCurrentBlock();

        $edit_event_form_tpl->setCurrentBlock('filter');
        $edit_event_form_tpl->setVariable('FILTER', $filter_html);
        $edit_event_form_tpl->parseCurrentBlock();

        $edit_event_form_tpl->setCurrentBlock('listing');
        $edit_event_form_tpl->setVariable('LISTING', $listing_html);
        $edit_event_form_tpl->parseCurrentBlock();

        return $edit_event_form_tpl->get();
    }

    /**
     * Render the listing layout for creation form context.
     *
     * Replaces placeholder footer with listing content and inserts it into
     * the create form template together with filters.
     *
     * @param string $listing_html Rendered listing contents.
     * @param string $filter_html Rendered filter controls HTML.
     * @return string Final rendered HTML for create view.
     */
    protected function renderListingWithingForm(string $listing_html, string $filter_html): string
    {
        $new_event_form_tpl = new ilTemplate(
            $this->plugin->getDirectory() . '/templates/default/tpl.OpencastEventCreate.html',
            true,
            true
        );

        $new_event_form_tpl->setCurrentBlock('filter');
        $new_event_form_tpl->setVariable('FILTER', $filter_html);
        $new_event_form_tpl->parseCurrentBlock();

        $new_event_form_tpl->setCurrentBlock('form');
        $footer_replace_tpl = new ilTemplate(
            $this->plugin->getDirectory() . '/templates/default/tpl.OpencastEventFooterReplace.html',
            true,
            false
        );
        $event_listing_replace_tpl = new ilTemplate(
            $this->plugin->getDirectory() . '/templates/default/tpl.OpencastEventFormEventListingReplace.html',
            true,
            true
        );

        $event_listing_replace_tpl->setVariable('LISTING', $listing_html);
        $event_listing_replace_tpl->setVariable('FOOTER', $footer_replace_tpl->get());

        $form_with_listing = str_replace(
            $footer_replace_tpl->get(), $event_listing_replace_tpl->get(), $this->form->getHTML()
        );
        $new_event_form_tpl->setVariable('FORM', $form_with_listing);
        $new_event_form_tpl->parseCurrentBlock();

        return $new_event_form_tpl->get();
    }

    /**
     * Build the controller URL for list actions (filter/sort/pagination).
     *
     * Preserves the currently selected sort/page values and sets
     * controller parameters based on context (new or edit form).
     *
     * @param string $for One of: 'pagination', 'filter', 'sortation'
     * @return string Generated link target for given action
     */
    protected function getActionLink(string $for): string
    {
        $this->dic->ctrl()->clearParameters($this->gui);
        $cmd = 'editEvent';
        if ($this->is_new) {
            $cmd = 'create';
            $this->dic->ctrl()->setParameter($this->gui, 'new_type', $this->gui->getType());
        } else {
            $this->dic->ctrl()->setParameter($this->gui, 'change_event', true);
        }
        if (in_array($for, ['pagination', 'filter'])) {
            $current_sort = $this->getListingSortValue();
            $this->dic->ctrl()->setParameter($this->gui, self::F_SORT_QUERY_PARAM, $current_sort);
        }
        if (in_array($for, ['sortation', 'filter'])) {
            $current_page = $this->getCurrentPage();
            $this->dic->ctrl()->setParameter($this->gui, self::F_PAGINATION_QUERY_PARAM, $current_page);
        }
        return $this->dic->ctrl()->getLinkTarget($this->gui, $cmd);
    }

    /**
     * Transform raw filter input into Opencast API filter parameters.
     *
     * - always includes processed status
     * - adds title, series, and date range filters when defined
     *
     * @param array $filter_data Filter values from UI filter component
     * @return array API-ready filter map
     */
    protected function buildFilterArray(array $filter_data): array
    {
        $filter = ['status' => 'EVENTS.EVENTS.STATUS.PROCESSED'];

        if ($title_filter = $filter_data[self::F_TEXTFILTER]) {
            $filter[self::F_TEXTFILTER] = $title_filter;
        }

        if ($series_filter = $filter_data[self::F_SERIES]) {
            $filter[self::F_SERIES] = $series_filter;
        }

        if ($start = $filter_data[self::F_START]) {
            $has_dt_filter = false;

            $start_filter_from = '1970-01-01T00:00:00';
            if (!empty($start[0])) {
                $has_dt_filter = true;
                $datetime = new \DateTimeImmutable($start[0]);
                $start_filter_from = $datetime->format('Y-m-d\TH:i:s');
            }

            $start_filter_to = '2200-01-01T00:00:00';
            if (!empty($start[1])) {
                $has_dt_filter = true;
                $datetime = new \DateTimeImmutable($start[1]);
                $start_filter_to = $datetime->format('Y-m-d\T23:59:59');
            }

            if ($has_dt_filter) {
                $filter[self::F_START] = $start_filter_from . '/' . $start_filter_to;
            }
        }

        return $filter;
    }

    /**
     * Convert a sort key from listing controls into API sort syntax.
     *
     * Accepts sort values like "start_date_asc", "title_desc", etc.
     * When valid, it returns "field:direction" for the API (e.g. "start_date:asc").
     * Invalid or empty sort values return an empty string (no sorting).
     *
     * @param string $sort_value Sort key from URL query or UI state
     * @return string API-compatible sort expression or empty string
     */
    protected function buildSortString(string $sort_value): string
    {
        if (empty($sort_value)) {
            return '';
        }
        $available_sorts = ['title', 'location', 'series_name', 'start_date'];
        $available_sort_directions = ['asc', 'desc'];
        $sort_field = str_replace(['_asc', '_desc'], ['', ''], $sort_value);
        $sort_direction = str_replace("{$sort_field}_", '', $sort_value);
        if (
            in_array($sort_direction, $available_sort_directions) &&
            in_array($sort_field, $available_sorts)
        ) {
            return "$sort_field:$sort_direction";
        }

        return '';
    }
}
