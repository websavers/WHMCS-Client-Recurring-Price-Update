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

        $dt .= '<form action="' . $modulelink . '" method="POST" style="padding:25px;box-shadow: rgba(0, 0, 0, 0.35) 0px 5px 15px;width:fit-content">';

        $products = localAPI('GetProducts');
        $product_options = "";
        foreach ($products['products']['product'] as $p){
            $product_options .= "<option value=\"${p['pid']}\">${p['name']}</option>";
        }
            
        $dt .= '<label>Product Type</label><br/><select id="product_type" value="all" class="custom-select">
            <option value="all">All</option>
            <option value="hosting">Hosting</option>
            <option value="addon">Addons</option>
            <option value="domain">Domain</option>
         </select><br/>';

        $dt .= '<label>Product</label><br/><select id="product_id" value="all" class="custom-select">
            <option value="all">All</option>' . $product_options . '</select><br/>';

        $dt .= '<label>Currency</label><br/><select id="currencyId" value="0" class="custom-select">';
            
        foreach(Capsule::table('tblcurrencies')->get() as $currencies){
            $dt.='<option value="' . $currencies->id . '">' . $currencies->code . '</option>';
        }

        $dt .= '<option value="0">All Currencies</option>';
     
        $dt .= '</select><br/><hr/>';
        $dt .= '<input type="hidden" name="action" value="submit"/>
            <button type="submit" id="updatePrices">Update User Price &raquo;</button>
            </form><br/>';


        return <<<EOF

<p>This tool updates the recurring price for all client services using WHMCS Auto Recalculate feature.</p>

<p>
    {$dt}
</p>

<style>
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
        $modulelink = $vars['modulelink'];
        $version    = $vars['version']; 
        $LANG       = $vars['_lang']; 
        $dt         = "";

        if($currId > 0){

            if (!empty($productID)){
                // TODO: This should eventually become a JOIN statement rather than nested queries
                foreach(Capsule::table('tblclients')->where('currency', '=', $currId)->pluck('id') as $userid){ 
                    foreach (Capsule::table('tblhosting')->where(array(
                        ['userid', '=', $userid],
                        ['packageid', '=', $productId],
                        ['domainstatus', 'IN', ['Active','Suspended']],
                    ))->pluck('id') as $serviceId) {
                        localAPI('UpdateClientProduct', array('serviceid' => $serviceId, 'autorecalc' => true));
                    }
                }
                $dt .= '<h4>Update completed for clients hosting plans using the selected product and with currency: ' . $_POST['currencyCode'] . '</h4><br/>';
            }
            else{
                foreach(Capsule::table('tblclients')->where('currency', '=', $currId)->pluck('id') as $userid){
                    if($product && ($product == "All" || $product == "hosting")){
                        foreach (Capsule::table('tblhosting')->where('userid', '=', $userid)->pluck('id') as $serviceId) {
                            localAPI('UpdateClientProduct', array('serviceid' => $serviceId, 'autorecalc' => true));
                        }
                    }
                    if($product && ($product == "All" || $product == "addon")){
                        foreach (Capsule::table('tblhostingaddons')->where('userid', '=', $userid)->pluck('id') as $serviceAddonId) {
                            localAPI('UpdateClientAddon', array('id' => $serviceAddonId, 'autorecalc' => true));
                        }
                    }
                    if($product && ($product == "All" || $product == "domain")){
                        foreach (Capsule::table('tbldomains')->where('userid', '=', $userid)->pluck('id') as $domainId) {
                            localAPI('UpdateClientDomain', array('domainid' => $domainId, 'autorecalc' => true));
                        }
                    }
                }
                $dt .= '<h4>Update completed for clients with currency: ' . $_POST['currencyCode'] . '</h4><br/>';
            }

        }
        else{
            if (!empty($productID)){
                foreach (Capsule::table('tblhosting')->where(array(
                    ['packageid', '=', $productId],
                    ['domainstatus', 'IN', ['Active','Suspended']],
                ))->pluck('id') as $serviceId) {
                    localAPI('UpdateClientProduct', array('serviceid' => $serviceId, 'autorecalc' => true));
                }
                $dt .= '<h4>Update completed for clients hosting plans using the selected product</h4><br/>';
            }
            else{
                foreach(Capsule::table('tblclients')->pluck('id') as $userid){
                    if($product && ($product == "All" || $product == "hosting")){
                        foreach (Capsule::table('tblhosting')->where('userid', '=', $userid)->pluck('id') as $serviceId) {
                            localAPI('UpdateClientProduct', array('serviceid' => $serviceId, 'autorecalc' => true));
                        }
                    }
                    if($product && ($product == "All" || $product == "addon")){
                        foreach (Capsule::table('tblhostingaddons')->where('userid', '=', $userid)->pluck('id') as $serviceAddonId) {
                            localAPI('UpdateClientAddon', array('id' => $serviceAddonId, 'autorecalc' => true));
                        }
                    }
                    if($product && ($product == "All" || $product == "domain")){
                        foreach (Capsule::table('tbldomains')->where('userid', '=', $userid)->pluck('id') as $domainId) {
                            localAPI('UpdateClientDomain', array('domainid' => $domainId, 'autorecalc' => true));
                        }
                    }        
                }
                $dt .= '<h4>Update completed for all clients</h4><br/>';
            }
        }
   
        $dt.='<br/><br/><a href="'.$modulelink.'" style="text-decoration: none;display: inline-block;padding: 8px 16px;background-color: #f1f1f1;color: black;border-radius: 20%;">&laquo; Go Back </a>';

        return $dt;

    }
}
