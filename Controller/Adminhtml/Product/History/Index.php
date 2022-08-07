<?php

namespace Compass\StockIntegration\Controller\Adminhtml\Product\History;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    protected $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Compass_StockIntegration::product_history');
        $resultPage->addBreadcrumb(__('Product History'), __('Product History'));
        $resultPage->getConfig()->getTitle()->prepend(__('Stock Integration / Product History'));

        return $resultPage;
    }
}
