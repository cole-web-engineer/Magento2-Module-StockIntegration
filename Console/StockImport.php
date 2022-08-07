<?php

namespace Compass\StockIntegration\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Compass\StockIntegration\Helper\Data as StockIntegrationHelper;

class StockImport extends Command
{
    protected $_stockIntegrationHelper;

    public function __construct(
        StockIntegrationHelper $stockIntegrationHelper
    ) {
        $this->_stockIntegrationHelper = $stockIntegrationHelper;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('compass:stock_import_run');
        $this->setDescription('Stock Import Run');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Started");

        try {
            
            $response = $this->_stockIntegrationHelper->run();

            if(isset($response['success_message'])) {
                
                if(is_array($response['success_message'])) {
                    
                    foreach($response['success_message'] as $message) {
                        $output->writeln($message);                    
                    }        
                    
                } else {
                    $output->writeln($response['error_message']);
                }
            }
            
            if(isset($response['error_message'])) {
                
                if(is_array($response['error_message'])) {
                    
                    foreach($response['error_message'] as $message) {
                        $output->writeln($message);                    
                    }        
                    
                } else {
                    $output->writeln($response['error_message']);
                }
            }
            
            $output->writeln("Finished");            

        } catch (LocalizedException $e) {
             die($e->getMessage());
        } catch (Throwable $e) {
            die(__('Something went wrong while stock import.'));
        }
    }
}
