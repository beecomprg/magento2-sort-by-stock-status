<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Beecom\SortByStockStatus\Model;

use Magento\Catalog\Model\Config;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogSearch\Model\ResourceModel\AdvancedFactory;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Catalog advanced search model
 * @method int getEntityTypeId()
 * @method \Magento\CatalogSearch\Model\Advanced setEntityTypeId(int $value)
 * @method int getAttributeSetId()
 * @method \Magento\CatalogSearch\Model\Advanced setAttributeSetId(int $value)
 * @method string getTypeId()
 * @method \Magento\CatalogSearch\Model\Advanced setTypeId(string $value)
 * @method string getSku()
 * @method \Magento\CatalogSearch\Model\Advanced setSku(string $value)
 * @method int getHasOptions()
 * @method \Magento\CatalogSearch\Model\Advanced setHasOptions(int $value)
 * @method int getRequiredOptions()
 * @method \Magento\CatalogSearch\Model\Advanced setRequiredOptions(int $value)
 * @method string getCreatedAt()
 * @method \Magento\CatalogSearch\Model\Advanced setCreatedAt(string $value)
 * @method string getUpdatedAt()
 * @method \Magento\CatalogSearch\Model\Advanced setUpdatedAt(string $value)
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @api
 * @since 100.0.2
 */
class Advanced extends \Magento\CatalogSearch\Model\Advanced
{
    /**
     * @var \Beecom\SortByStockStatus\Helper\Data
     */
    protected $helper;

    public function __construct(Context $context, Registry $registry, AttributeCollectionFactory $attributeCollectionFactory, Visibility $catalogProductVisibility, Config $catalogConfig, CurrencyFactory $currencyFactory, ProductFactory $productFactory, StoreManagerInterface $storeManager, ProductCollectionFactory $productCollectionFactory, AdvancedFactory $advancedFactory, \Beecom\SortByStockStatus\Helper\Data $helper, array $data = [])
    {
        $this->helper = $helper;
        parent::__construct($context, $registry, $attributeCollectionFactory, $catalogProductVisibility, $catalogConfig, $currencyFactory, $productFactory, $storeManager, $productCollectionFactory, $advancedFactory, $data);
    }

    /**
     * Prepare product collection
     *
     * @param Collection $collection
     * @return $this
     */
    public function prepareProductCollection($collection)
    {
        $collection
            ->addAttributeToSelect($this->_catalogConfig->getProductAttributes())
            ->setStore($this->_storeManager->getStore())
            ->addMinimalPrice()
            ->addTaxPercents()
            ->addStoreFilter()
            ->setVisibility($this->_catalogProductVisibility->getVisibleInSearchIds());

        if (!$this->helper->isEnabled()) return $this;

        $collection->unshiftOrder(
            'stock.is_in_stock',
            \Magento\Catalog\Model\ResourceModel\Product\Collection::SORT_ORDER_DESC
        );

        // Follows code necessary for loading product collection without ES

        // website_id=0 is currect value. The table cataloginventory_stock_status contains all records with website_id=0.
        // Alternatively in a future version of Magento they can change their minds and website_id=0 is no more true
        // then you should be able to use $website_id = $this->helper->getCurrentWebsiteId() or similar.
        $website_id = 0;

        // we need to ensure joining just once
        $fromPart = $collection->getSelect()->getPart(\Zend_Db_Select::FROM);

        if (!isset($fromPart['bee_stock_status'])) {
            $conditions = array(
                'bee_stock_status.product_id = e.entity_id',
                $collection->getConnection()->quoteInto('bee_stock_status.website_id=?', $website_id)
            );

            $joinCond = join(' AND ', $conditions);

            $collection
                ->getSelect()
                ->joinLeft(
                    array('bee_stock_status' => $collection->getResource()->getTable('cataloginventory_stock_status')),
                    $joinCond,
                    []
                );

            $orderPart = $collection->getSelect()->getPart(\Zend_Db_Select::ORDER);

            // we need to ensure that sorting by stock status is always first ordering
            $orderPart = array_merge(
                [[
                    0 => 'bee_stock_status.stock_status',
                    1 => \Zend_Db_Select::SQL_DESC
                ]],
                $orderPart
            );

            $collection->getSelect()->setPart(\Zend_Db_Select::ORDER, $orderPart);
        }

        return $this;
    }
}
