<?php

declare(strict_types=1);

namespace Icube\ExamOms\Helper;

use DateTime;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class GraphQlSearchCriteria extends AbstractHelper
{
    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var FilterGroupBuilder
     */
    private $filterGroupBuilder;

    /**
     * @var SearchCriteriaFactory
     */
    private $searchCriteriaFactory;

    /**
     * @var SortOrderBuilder
     */
    private $sortOrderBuilder;

    /**
     * @var array
     */
    protected $columns = [];

    /**
     * @var TimezoneInterface
     */
    protected $timezoneInterface;

    /**
     * @var PricingHelper
     */
    protected $pricingHelper;

    /**
     * GetTrainerById constructor.
     *
     * @param Context $context
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param SearchCriteriaFactory $searchCriteriaFactory
     * @param SortOrderBuilder $sortOrderBuilder
     * @param TimezoneInterface $timezoneInterface
     * @param PricingHelper $pricingHelper
     */
    public function __construct(
        Context $context,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        SearchCriteriaFactory $searchCriteriaFactory,
        SortOrderBuilder $sortOrderBuilder,
        TimezoneInterface $timezoneInterface,
        PricingHelper $pricingHelper
    ) {
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->searchCriteriaFactory = $searchCriteriaFactory;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->timezoneInterface = $timezoneInterface;
        $this->pricingHelper = $pricingHelper;
        parent::__construct($context);
    }

    /**
     * Get search criteria instance.
     *
     * @return SearchCriteriaInterface
     */
    public function getSearchCriteria(): SearchCriteriaInterface
    {
        return $this->searchCriteriaFactory->create();
    }

    /**
     * build
     *
     * @param array $args
     */
    public function build(array $args): SearchCriteriaInterface
    {
        $searchCriteria = $this->searchCriteriaFactory->create();
        if (isset($args['search']) && !empty($args['search'])) {
            foreach ($this->getColumns() as $column) {
                $this->addFilter($column, "%" . $args['search'] . "%", "like");
            }
            $this->setFilterGroups($searchCriteria);
        }

        $filters = is_array(@$args['filter']) ? $args['filter'] : [];
        foreach ($filters as $fieldName => $filter) {
            foreach ($filter as $conditionType => $value) {
                if ($conditionType == 'like') {
                    $this->addFilter($fieldName, "%" . $value . "%", $conditionType);
                    $this->setFilterGroups($searchCriteria);
                } else {
                    if (is_string($value) && $this->isDateValid($value) !== false) {
                        $value = $this->timezoneInterface->convertConfigTimeToUtc($value);
                    }
                    $this->addFilter($fieldName, $value, $conditionType);
                    $this->setFilterGroups($searchCriteria);
                }
            }
        }

        $sorts = is_array(@$args['sort']) ? $args['sort'] : [];
        foreach ($sorts as $sortField => $sortDirection) {
            $this->addSortOrder($searchCriteria, $sortField, $sortDirection);
        }

        $searchCriteria->setPageSize(@$args['pageSize']);
        $searchCriteria->setCurrentPage(@$args['currentPage']);

        return $searchCriteria;
    }

    /**
     * Check if date is valid
     *
     * @param string $date
     * @param string $format
     * @return bool
     */
    public function isDateValid($date, $format = "Y-m-d H:i:s"): bool
    {
        $dateFormat = DateTime::createFromFormat($format, $date);
        return $dateFormat && $dateFormat->format($format) === $date;
    }

    /**
     * Add filter to search criteria
     *
     * @param string $field
     * @param mixed $value
     * @param string $conditionType
     */
    public function addFilter(
        string $field,
        $value,
        $conditionType
    ) {
        $filter = $this->filterBuilder
            ->setField($field)
            ->setValue($value)
            ->setConditionType($conditionType)
            ->create();
        $this->filterGroupBuilder->addFilter($filter);
        return $this;
    }

    /**
     * Add filter to search criteria
     *
     * @param string $field
     * @param mixed $value
     * @param string $conditionType
     * @param SearchCriteriaInterface $searchCriteria
     */
    public function addOptionalFilter(
        string $field,
        $value,
        $conditionType,
        SearchCriteriaInterface $searchCriteria
    ): void {
        $filter = $this->filterBuilder
            ->setField($field)
            ->setValue($value)
            ->setConditionType($conditionType)
            ->create();
        $this->filterGroupBuilder->addFilter($filter);
        $this->setFilterGroups($searchCriteria);
    }

    /**
     * Set filter groups
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return $this
     */
    public function setFilterGroups(SearchCriteriaInterface $searchCriteria)
    {
        $filterGroups = $searchCriteria->getFilterGroups();
        $filterGroups[] = $this->filterGroupBuilder->create();
        $searchCriteria->setFilterGroups($filterGroups);
        return $this;
    }

    /**
     * Sort by id ASC by default
     *
     * @param SearchCriteriaInterface $searchCriteria
     */
    public function addSortOrder(
        SearchCriteriaInterface $searchCriteria,
        string $sortField,
        $sortDirection
    ) {
        $defaultSortOrder = $this->sortOrderBuilder
            ->setField($sortField)
            ->setDirection($sortDirection)
            ->create();

        $searchCriteria->setSortOrders([$defaultSortOrder]);
        return $this;
    }

    /**
     * Set column of table
     *
     * @param array $columns
     * @return $this
     */
    public function setColumns(array $columns)
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Get column of table
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get locale date
     *
     * @return \DateTime|mixed
     */
    public function getLocalDate($date, $format = '')
    {
        if (is_null($date) || empty($date) || !$this->isDateValid($date)) return $date;

        try {
            $dateTime = $this->timezoneInterface->date(new \DateTime($date));
            return empty($format) ? $dateTime : $dateTime->format($format);
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Get formatted price
     *
     * @param float|null $price
     * @return String
     */
    public function getFormattedPrice($price)
    {
        return $this->pricingHelper->currency($price, true, false);
    }
}
