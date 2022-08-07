A. Installation
 
  1. Get access to the Magento 2 server.

  2. Go to the Magento2 modules folder.

  3. Upload the all items into the zip file.
 
  4. Execute Magento setup upgrade

    $ bin/magento setup:upgrade

  5. Clean cache and generated code

    $ bin/magento cache:clean
    
    $ rm -rf var/generation/*

  6. Run magento compiler to generate auto-generated classes

    $ bin/magento setup:di:compile
    
B. Settings

    You can find it in Magento -> System -> Stock Integration -> Configuration link.
    
C. Running

    Application;

    1. It can be run via the command console (available for testing) with the following command
    
    $ bin/magento compass:stock_import_run

    2. It will run automatically with the cron service running every 10 minutes.