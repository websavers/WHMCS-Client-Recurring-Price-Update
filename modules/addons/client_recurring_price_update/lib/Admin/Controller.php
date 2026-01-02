<?php

namespace WHMCS\Module\Addon\client_recurring_price_update\Admin;
use WHMCS\Database\Capsule;

class Controller {

    public function index($vars)
    {
        $modulelink = $vars['modulelink']; 
        $version    = $vars['version']; 
        $LANG       = $vars['_lang'];
        $dt         = '';
        $product_options = "";
        $currency_options = "";

        $products = localAPI('GetProducts');
        foreach ($products['products']['product'] as $p){
            $product_options .= "<option value='" . $p['pid'] . "'>" . $p['name'] . "</option>";
        }
        foreach(Capsule::table('tblcurrencies')->get() as $currencies){
            $currency_options .= '<option value="' . $currencies->id . '">' . $currencies->code . '</option>';
        }
        $currency_options .= '<option value="0">All Currencies</option>';

        $dt .= '<form id="crpu" action="' . $modulelink . '" method="POST">';

        $dt .= "<input type='checkbox' name='testrun' id='testrun' checked='checked' /> Test Run";

        $dt .= "<label>Currency</label><br/><select id='currencyId' value='0' class='custom-select'>$currency_options</select><br/>";

        $dt .= '<hr/>';
            
        $dt .= '<label>Select by Service Type</label><br/><select id="product_type" value="all" class="custom-select">
            <option value="all">All</option>
            <option value="hosting">Hosting</option>
            <option value="addon">Addons</option>
            <option value="domain">Domain</option>
         </select>';

        $dt .= '<div style="text-align:center;margin-top:10px;">&mdash; OR &mdash;</div>';
        $dt .= '<label>Select by Product</label><br/><select id="product_id" value="all" class="custom-select">
            <option value="all">All</option>' . $product_options . '</select><br/>';

        $dt .= '<hr/>';
        $dt .= '<input type="hidden" name="action" value="submit"/>
            <button type="submit" id="updatePrices">Update service prices &raquo;</button>
            </form><br/>';


        return <<<EOF

<p>This tool updates the recurring price for all client services using WHMCS Auto Recalculate feature.</p>

<p>
    {$dt}
</p>

<style>
form#crpu{
    padding: 25px;
    box-shadow: rgba(0, 0, 0, 0.35) 0px 5px 5px;
    width: fit-content;
}
select.custom-select{
  width: 250px;
  min-width: 15ch;
  max-width: 50ch;
  border: 1px solid;
  border-radius: 0.25em;
  padding: 0.25em 0.5em;
  font-size: 1.25rem;
  cursor: pointer;
  line-height: 1.1;
  background-color: #fff;
  background-image: linear-gradient(to top, #f9f9f9, #fff 33%);
}
button#updatePrices{
    text-decoration: none;
    display: inline-block;
    padding: 8px 16px;
    background-color: #f1f1f1;
    color: black;
}
</style>

EOF;


    }


    public function submit($post)
    {
        $currId     = $post['currencyId'];
        $product    = $post['product_type'];
        $productID  = $post['product_id'];
        $testrun    = isset($post['testrun'])? true:false;
        $modulelink = $vars['modulelink'];
        $version    = $vars['version']; 
        $LANG       = $vars['_lang']; 
        $dt         = "";

        $updated_serviceIds = array();
        $updated_addonIds = array();
        $updated_domainIds = array();

        if($currId > 0){

            if (!empty($productID)){
                // TODO: This should eventually become a JOIN statement rather than nested queries
                foreach(Capsule::table('tblclients')->where('currency', '=', $currId)->pluck('id') as $userid){ 
                    foreach (Capsule::table('tblhosting')->where(array(
                        ['userid', '=', $userid],
                        ['packageid', '=', $productId],
                        ['domainstatus', 'IN', ['Active','Suspended']],
                    ))->pluck('id') as $serviceId) {
                        $updated_serviceIds[] = $serviceId;
                        if (!$testrun) localAPI('UpdateClientProduct', array('serviceid' => $serviceId, 'autorecalc' => true));
                    }
                }
                $dt .= '<h4>Update completed for clients hosting plans created from product ID ' . $productID . ' and using currency: ' . $_POST['currencyCode'] . '</h4><br/>';
            }
            else{
                foreach(Capsule::table('tblclients')->where('currency', '=', $currId)->pluck('id') as $userid){
                    if($product && ($product == "All" || $product == "hosting")){
                        foreach (Capsule::table('tblhosting')->where('userid', '=', $userid)->pluck('id') as $serviceId) {
                            $updated_serviceIds[] = $serviceId;
                            if (!$testrun) localAPI('UpdateClientProduct', array('serviceid' => $serviceId, 'autorecalc' => true));
                        }
                    }
                    if($product && ($product == "All" || $product == "addon")){
                        foreach (Capsule::table('tblhostingaddons')->where('userid', '=', $userid)->pluck('id') as $serviceAddonId) {
                            $updated_addonIds[] = $serviceAddonId;
                            if (!$testrun) localAPI('UpdateClientAddon', array('id' => $serviceAddonId, 'autorecalc' => true));
                        }
                    }
                    if($product && ($product == "All" || $product == "domain")){
                        foreach (Capsule::table('tbldomains')->where('userid', '=', $userid)->pluck('id') as $domainId) {
                            $updated_domainIds[] = $domainId;
                            if (!$testrun) localAPI('UpdateClientDomain', array('domainid' => $domainId, 'autorecalc' => true));
                        }
                    }
                }
                $dt .= '<h4>Update completed for clients with currency: ' . $_POST['currencyCode'] . '</h4>';
            }

        }
        else{

            if (!empty($productID)){
                foreach (Capsule::table('tblhosting')->where(array(
                    ['packageid', '=', $productId],
                    ['domainstatus', 'IN', ['Active','Suspended']],
                ))->pluck('id') as $serviceId) {
                    $updated_serviceIds[] = $serviceId;
                    if (!$testrun) localAPI('UpdateClientProduct', array('serviceid' => $serviceId, 'autorecalc' => true));
                }
                $dt .= '<h4>Update completed for clients hosting plans using the selected product</h4><br/>';
            }
            else{
                foreach(Capsule::table('tblclients')->pluck('id') as $userid){
                    if($product && ($product == "All" || $product == "hosting")){
                        foreach (Capsule::table('tblhosting')->where('userid', '=', $userid)->pluck('id') as $serviceId) {
                            $updated_serviceIds[] = $serviceId;
                            if (!$testrun) localAPI('UpdateClientProduct', array('serviceid' => $serviceId, 'autorecalc' => true));
                        }
                    }
                    if($product && ($product == "All" || $product == "addon")){
                        foreach (Capsule::table('tblhostingaddons')->where('userid', '=', $userid)->pluck('id') as $serviceAddonId) {
                            $updated_addonIds[] = $serviceAddonId;
                            if (!$testrun) localAPI('UpdateClientAddon', array('id' => $serviceAddonId, 'autorecalc' => true));
                        }
                    }
                    if($product && ($product == "All" || $product == "domain")){
                        foreach (Capsule::table('tbldomains')->where('userid', '=', $userid)->pluck('id') as $domainId) {
                            $updated_domainIds[] = $domainId;
                            if (!$testrun) localAPI('UpdateClientDomain', array('domainid' => $domainId, 'autorecalc' => true));
                        }
                    }        
                }
                $dt .= '<h4>Update completed for all clients</h4><br/>';
            }

        }

        $dt .= 'Service IDs: ' . implode(',', $updated_serviceIds);
        $dt .= 'Addon IDs: ' . implode(',', $updated_addonIds);
        $dt .= 'Domain IDs: ' . implode(',', $updated_domainIds);
        $dt .= '<br/><br/><a href="'.$modulelink.'" style="text-decoration: none;display: inline-block;padding: 8px 16px;background-color: #f1f1f1;color: black;border-radius: 20%;">&laquo; Go Back </a>';

        return $dt;

    }
}
