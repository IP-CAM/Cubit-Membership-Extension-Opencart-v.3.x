Installation Guide for Cubit extension
====
# Pre-requisites prior to installing the plugin
  1. The plugin supports these OpenCart versions 3.0.2.0 and higher

  2. Remove all existing Cubit implementations from the website.

# Plugin installation
  1. Install the Cubit for OpenCart plugin via OCMOD.
      - Navigate to the admin panel of OpenCart and click on:
        - Menu -> Extensions -> Installer. 
      - Click on the Upload button and select the plugin file.
      - Navigate to the admin panel of OpenCart and click on Menu -> Extensions -> Modifications.
      - Click on the Refresh button.

  2. Now you need to perform an additional installation step.
      - Navigate to Menu -> Extensions -> Extensions.
      - Click on the Extension type dropdown list and select Modules.
      - Scroll down the list to locate Cubit and click on Install button.
  
  3. Setup the permission rights for Cubit Extension if you encounter "Permission Denied".

  4. Configure plugin.
      - add Paypal Client ID and Paypal Secret
      - refresh webhook id (avoid having two listeners attached!)
      - add or edit existing offers
      - change module status to "Enabled"
      - save

  5. Add cron that request reload of page http://YOURSTORE/index.php?route=extension/module/cubit/cron every 5 minutes (depends of business needs)

  6. Send customers to membership salepoint: http://YOURSTORE/index.php?route=extension/module/cubit/salepoint

If your configuration was correct you should see members being uplifted to customer groups defined previously. You should also see Paypal email notifications coming in about complete or pending payments. All this should happend in minutes after purchase (depending on cron configuration and Paypal server idle).

WARNING: Cubit accepts only complete payments.

# Membership mangement

Navigate to Menu -> Customers -> Memberships to see a detailed list of existing memberships. You can sort them by customer name, offer, date added, expiration date, current status. You can also filter listing by store, customer name, offer, paypal subscription id, paypal subscription status and membership status.

If you want to see transactions, cancel active or suspended memberships, or change membership expiration dates click on "Details" button.

WARNING: If you uninstall extension all memberships are lost!