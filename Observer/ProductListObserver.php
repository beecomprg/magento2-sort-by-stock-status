<?php

namespace Beecom\SortByStockStatus\Observer;

use Magento\Framework\Event\ObserverInterface;

class ProductListObserver implements ObserverInterface
{
    /**
     * @var \Beecom\SortByStockStatus\Helper\Data
     */
    protected $helper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * constructor
     *
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Beecom\SortByStockStatus\Helper\Data $helper
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Beecom\SortByStockStatus\Helper\Data $helper
    ) {
        $this->logger = $logger;
        $this->helper = $helper;
    }

  public function execute(\Magento\Framework\Event\Observer $observer)
  {
        if (!$this->helper->isEnabled()) return;

        $collection = $observer->getData('collection');

        // Smile\ElasticsuiteCatalog\Model\ResourceModel\Product\Fulltext\Collection operates with
        // Elastic Search and E index contains stock information if form of object.
        // Stock status information is available in nested field stock.is_in_stock
        //
        // How to check that field is present?
        // There is no way around it, at least $collection does no provide a method to retrive it.
        // But if field is not present it does not fail. So not a big deal.
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
  }
}
