# Vaimo_MaksuturvaMasterpass

This is Maksuturva's official Magento 1.x Masterpass Best Practice express checkout module. The module is maintained by Vaimo.

## System requirements

The Maksuturva Masterpass module for Magento was tested on and requires the following set of applications in order to fully work:

* Magento 1.9.x or Magento EE 1.14.x
* PHP version 5
* Module vaimo/maksuturva installed

There is no guarantee that the module is fully functional in any other environment which does not fulfill the requirements.
Even though Maksuturva base module must be installed, the Maksuturva payment method does not have to be enabled nor
does the module need to be configured.
## Installation

Prior to any change in your environment, it is strongly recommended to perform a full backup of the entire Magento installation.
It is also strongly recommended to do installation first in development environment, and only after that in production environment.

1. Extract the module files under Magento installation
2. Clean Magento cache
3. Disable required state for Finland in configuration (General > General > States Options)
4. Configure the module
5. Verify the payments work

At this moment the module does not alter Magento's database schema in any way or create any custom database tables.


## Configuration

Configuration for the module can be found from standard location under *System -> Configuration -> Payment Methods -> Maksuturva Masterpass Best Practice.

##### Sandbox mode
If enabled, communication url, seller id and secret key in sandbox fields are used, otherwise production parameters are used.

##### Seller id and secret key
This parameter provided by Maksuturva.
Please note that this key must not be shared with any person, since it allows many operations to be done in your Maksuturva account.

##### Communication url

API url to communicate with Maksuturva service. Should be usually kept as is. Note that this can differ from Maksuturva base module communication url.

##### Key Version
This parameter provided by Maksuturva. Check your secret key version and input this value into the configuration field.

## Discount
Discount should be set in Maksuturva module discount setting (FI55=10 for example). Setting discount for Masterpass Best Practice payment method (masterpasspb) will not set it for Masterpass in normal checkout.

## Sandbox testing

Sandbox mode uses staged payment page. Instructions for testing can be found at https://developer.mastercard.com/page/masterpass-testing#global-excluding-us
In the test environment no actual money is handled.

## Template modifications

Module overwrites default templates for checkout login step for Onepage CE/EE and Vaimo checkout to include a Masterpass button.
If these templates are overridden, you need to edit the template to include the Masterpass button.