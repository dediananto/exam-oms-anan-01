<?php

declare(strict_types=1);

namespace Icube\ExamOms\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Swiftoms\Company\Api\CompanyRepositoryInterface;
use Swiftoms\General\Helper\GraphQlSearchCriteria;
use Swiftoms\Product\Model\ProductRepository;
use Magento\Catalog\Model\Product\Type;

class GetProductList implements ResolverInterface
{
    /**
     * @var ProductRepository
     */
    protected $productRepository;

    /**
     * @var PricingHelper
     */
    protected $priceHelper;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var CompanyRepositoryInterface
     */
    protected $companyRepository;

    /**
     * @var GraphQlSearchCriteria
     */
    protected $searchCriteriaHelper;

    /**
     * @param ProductRepository $productRepository
     * @param PricingHelper $priceHelper
     * @param CustomerRepositoryInterface $customerRepository
     * @param CompanyRepositoryInterface $companyRepository
     * @param GraphQlSearchCriteria $searchCriteriaHelper
     * @param Type $type
     */
    public function __construct(
        ProductRepository $productRepository,
        PricingHelper $priceHelper,
        CustomerRepositoryInterface $customerRepository,
        CompanyRepositoryInterface $companyRepository,
        GraphQlSearchCriteria $searchCriteriaHelper
    ) {
        $this->productRepository = $productRepository;
        $this->priceHelper = $priceHelper;
        $this->customerRepository = $customerRepository;
        $this->companyRepository = $companyRepository;
        $this->searchCriteriaHelper = $searchCriteriaHelper;
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        /** @var ContextInterface $context */
        if (false === $context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(
                __('The request is allowed for logged in')
            );
        }

        if ($args['currentPage'] < 1) {
            throw new GraphQlInputException(__('currentPage value must be greater than 0.'));
        }

        if ($args['pageSize'] < 1) {
            throw new GraphQlInputException(__('pageSize value must be greater than 0.'));
        }

        try {
            $customer = $this->customerRepository->getById($context->getUserId());
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()));
        }

        $searchCriteria = $this->searchCriteriaHelper->build($args);
        $searchResult = $this->productRepository->getList($searchCriteria);

        $items = [];
        foreach ($searchResult->getItems() as $key => $item) {
            $status = [];
            switch ($item->getStatus()) {
                case 1:
                    $status['value'] = $item->getStatus();
                    $status['label'] = 'Enabled';
                    break;

                default:
                    $status['value'] = $item->getStatus();
                    $status['label'] = 'Disabled';
                    break;
            }

            $_item = [
                'entity_id' => $item->getId(),
                'name' => $item->getName(),
                'sku' => $item->getSku(),
                'price' => $this->priceHelper->currency($item->getPrice(), true, false),
                'description' => $item->getDescription(),
                'sort_description' => $item->getSortDescription(),
                'status' => $status,
                'weight' => $item->getWeight(),
                'dimension_package_height' => $item->getDimensionPackageHeight(),
                'dimension_package_length' => $item->getDimensionPackageLength(),
                'dimension_package_width' => $item->getDimensionPackageWidth()
            ];

            $items[] = $_item;
        }

        $data = [
            'total_count' => $searchResult->getTotalCount(),
            'items' => $items,
            'page_info' => [
                'page_size' => $searchCriteria->getPageSize(),
                'current_page' => $searchCriteria->getCurrentPage(),
                'total_pages' => ceil($searchResult->getTotalCount() / $searchCriteria->getPageSize())
            ]
        ];

        return $data;
    }
}
