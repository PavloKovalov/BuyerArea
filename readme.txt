BuyerArea is a plugin for SEOTOASTER CMS.
http://github.com/funky/BuyerArea

It's a simpliest CRM. Allows you to manage your clients, their details and view history of the payment.
Also, it has widget for client login and API for log client payment (it can be called for payment plugins you using after finalizing payment).

USAGE:

To use BuyerArea login widget place {$plugin:buyerarea:loginform}.
After login in through widget all client info will be populated on checkout page.
Note, this widget works same like {$member_login}, but no any additional data will be available for users who logged in with {$member_login}.

To log payment from you plugin look an example (for developers):

$websiteurl = 'http://www.example.com'; //should be assigned from
$buyerarea = RCMS_Core_PluginFactory::createPlugin('Buyerarea', null, array('websiteUrl' => $websiteUrl));
$buyerarea->logpayment(array('type'=>$reference, 'id'=>$ref_id));
// @var $reference - string - allowed 'quote' or 'cart'
// @var $ref_id	   - mixed (numeric) - id of quote/cart


BuyerArea plugin powered with DataTables (get from http://github.com/DataTables)
