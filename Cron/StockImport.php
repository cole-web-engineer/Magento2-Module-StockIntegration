<?php

namespace Compass\StockIntegration\Cron;

use Psr\Log\LoggerInterface;
use Compass\StockIntegration\Helper\Data as StockIntegrationHelper;

class StockImport
{
    protected $_logger;    
    protected $_stockIntegrationHelper;

    public function __construct(
        LoggerInterface $logger,
        StockIntegrationHelper $stockIntegrationHelper
    ) {
        $this->_logger = $logger;        
        $this->_stockIntegrationHelper = $stockIntegrationHelper;
    }

    public function execute(\Magento\Cron\Model\Schedule $schedule)
    {
        if(!$this->_stockIntegrationHelper->isCronEnabled()) return;
        
        try {
            $this->_stockIntegrationHelper->run();
        } catch (LocalizedException $e) {
            $this->_logger->error($e->getMessage());            
        } catch (Throwable $e) {
            $this->_logger->error(__('Something went wrong while update stock import: %1',$e->getMessage()));
        }

        return $this;
    }
}
