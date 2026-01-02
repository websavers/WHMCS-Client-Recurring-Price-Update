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

        $dt .= "<input type='checkbox' name='testrun' id='testrun' checked='checked' /> Test Run <br/>";

        $dt .= "<label>Currency</label><br/><select name='currencyId' id='currencyId' class='custom-select'>$currency_options</select><br/>";

        $dt .= '<hr/>';
            
        $dt .= '<label>Select by Service Type</label><br/><select name="product_type" id="product_type" value="all" class="custom-select">
            <option value="all">All</option>
            <option value="hosting">Hosting</option>
            <option value="addon">Addons</option>
            <option value="domain">Domain</option>
         </select>';

        $dt .= '<div style="text-align:center;margin-top:10px;">&mdash; OR &mdash;</div>';
        $dt .= '<label>Select by Product</label><br/><select name="product_id" id="product_id" value="all" class="custom-select">
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
        $productType = $post['product_type'];
        $productId  = $post['product_id'];
        $testrun    = isset($post['testrun'])? true:false;
        $modulelink = $vars['modulelink'];
        $version    = $vars['version']; 
        $LANG       = $vars['_lang']; 
        $dt         = ""; //Return Output

        $affected_serviceIds = array();
        $affected_addonIds = array();
        $affected_domainIds = array();

        //var_dump($testrun); return ''; ///DEBUG

        if ($testrun) $dt .= 'This is a test run. No services will actually be updated.<br/>';

        if ($currId > 0){

            if (!empty($productId)){
                $hosting_plans = Capsule::table('tblhosting')
                    ->join('tblclients', 'tblhosting.userid', '=', 'tblclients.id')
                    ->select('tblhosting.id')
                    ->where('tblclients.currency', '=', $currId)
                    ->where('tblhosting.packageid', '=', $productId)
                    ->whereIn('tblhosting.domainstatus', ['Active','Suspended'])
                    ->orderBy('tblhosting.id')
                    ->get();

                //return print_r($hosting_plans, true); //DEBUG
                foreach ($hosting_plans as $service){
                    $affected_serviceIds[] = $service->id;
                    if (!$testrun) localAPI('UpdateClientProduct', array('serviceid' => $service->id, 'autorecalc' => true));
                }
                if (!$testrun){
                    $dt .= "<div><strong>Update completed for clients hosting plans created from product ID $productId and currency ID $currId</strong></div>";
                }
            }
            else{
                foreach(Capsule::table('tblclients')->where('currency', '=', $currId)->pluck('id') as $userid){
                    if($productType && ($productType == "All" || $productType == "hosting")){
                        foreach (Capsule::table('tblhosting')->where('userid', '=', $userid)->pluck('id') as $serviceId) {
                            $affected_serviceIds[] = $serviceId;
                            if (!$testrun) localAPI('UpdateClientProduct', array('serviceid' => $serviceId, 'autorecalc' => true));
                        }
                    }
                    if($productType && ($productType == "All" || $productType == "addon")){
                        foreach (Capsule::table('tblhostingaddons')->where('userid', '=', $userid)->pluck('id') as $serviceAddonId) {
                            $affected_addonIds[] = $serviceAddonId;
                            if (!$testrun) localAPI('UpdateClientAddon', array('id' => $serviceAddonId, 'autorecalc' => true));
                        }
                    }
                    if($productType && ($productType == "All" || $productType == "domain")){
                        foreach (Capsule::table('tbldomains')->where('userid', '=', $userid)->pluck('id') as $domainId) {
                            $affected_domainIds[] = $domainId;
                            if (!$testrun) localAPI('UpdateClientDomain', array('domainid' => $domainId, 'autorecalc' => true));
                        }
                    }
                }
                if (!$testrun){
                    $dt .= "<div><strong>Update completed for all selected product types with currency ID $currId</strong></div>";
                }
            }
        }
        else{

            if (!empty($productId)){
                $hosting_plans = Capsule::table('tblhosting')
                    ->select('tblhosting.id')
                    ->where('tblhosting.packageid', '=', $productId)
                    ->whereIn('tblhosting.domainstatus', ['Active','Suspended'])
                    ->orderBy('tblhosting.id')
                    ->get();

                //return print_r($hosting_plans, true); //DEBUG
                foreach ($hosting_plans as $service){
                    $affected_serviceIds[] = $service->id;
                    if (!$testrun) localAPI('UpdateClientProduct', array('serviceid' => $service->id, 'autorecalc' => true));
                }
                if (!$testrun){
                    $dt .= "<div><strong>Update completed for clients hosting plans created from product ID $productId and currency ID $currId</strong></div>";
                }
            }
            else{
                if($productType && ($productType == "All" || $productType == "hosting")){
                    foreach (Capsule::table('tblhosting')->pluck('id') as $serviceId) {
                        $affected_serviceIds[] = $serviceId;
                        if (!$testrun) localAPI('UpdateClientProduct', array('serviceid' => $serviceId, 'autorecalc' => true));
                    }
                }
                if($productType && ($productType == "All" || $productType == "addon")){
                    foreach (Capsule::table('tblhostingaddons')->pluck('id') as $serviceAddonId) {
                        $affected_addonIds[] = $serviceAddonId;
                        if (!$testrun) localAPI('UpdateClientAddon', array('id' => $serviceAddonId, 'autorecalc' => true));
                    }
                }
                if($productType && ($productType == "All" || $productType == "domain")){
                    foreach (Capsule::table('tbldomains')->pluck('id') as $domainId) {
                        $affected_domainIds[] = $domainId;
                        if (!$testrun) localAPI('UpdateClientDomain', array('domainid' => $domainId, 'autorecalc' => true));
                    }
                }

                if (!$testrun){
                    $dt .= '<div><strong>Update completed for all selected product types.</strong></div>';
                }
            }

        }
        if ($testrun){
            $dt .= '<h4>If this were not a test run, the following would be changed:</h4>';
        }
        else{
            $dt .= '<h4>The following has been changed:</h4>';
        }

        $affected_serviceIds_c = count($affected_serviceIds);
        $affected_addonIds_c = count($affected_addonIds);
        $affected_domainIds_c = count($affected_domainIds);

        $dt .= '<ul>';
        if ($affected_serviceIds_c > 0)
            $dt .= '<li>Service IDs (' . $affected_serviceIds_c . '): <div style="max-width: 600px">' . implode(" ", $affected_serviceIds) . '</div></li>';
        if ($affected_addonIds_c > 0)
            $dt .= '<li>Addon IDs (' . $affected_addonIds_c . '): <div style="max-width: 600px">' . implode(" ", $affected_addonIds) . '</div></li>';
        if ($affected_domainIds_c > 0)
            $dt .= '<li>Domain IDs (' . $affected_domainIds_c . '): <div style="max-width: 600px">' . implode(" ", $affected_domainIds) . '</div></li>';
        $dt .= '</ul>';
        $dt .= '<br/><br/><a href="'.$modulelink.'" style="text-decoration: none;display: inline-block;padding: 8px 16px;background-color: #f1f1f1;color: black;border-radius: 5px;">&laquo; Go Back </a>';

        return $dt;

    }
}
