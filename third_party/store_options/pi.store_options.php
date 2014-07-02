<?php
/*
====================================================================================================
 Author: Peter Lewis - peter@peteralewis.com
 http://www.peteralewis.com
====================================================================================================
 This file must be placed in the /system/expressionengine/third_party/store_options folder
 package            Store Options
 version            Version 1.0.0
 copyright          Copyright (c) 2013 Peter Lewis
 license            Attribution No Derivative Works 3.0: http://creativecommons.org/licenses/by-nd/3.0/
 Last Update        April 2013
----------------------------------------------------------------------------------------------------
 Purpose: Outputs the options and stock for the specified Exp:resso Store managed Entry
====================================================================================================

Change Log

v1.0.0	Initial Version


*/

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
                    'pi_name'           => 'Exp:resso Store Options',
                    'pi_version'        => '1.0.0',
                    'pi_author'         => 'Peter Lewis',
                    'pi_author_url'     => 'http://www.peteralewis.com/',
                    'pi_description'    => 'Outputs the options and stock for the specified Exp:resso Store managed Entry.',
                    'pi_usage'          => Store_options::usage()
);

class Store_options {
    public $return_data;
    var $site_id;
    var $EE;

    //###   Constructor   ###
    function __construct() {
        //###   Get EE Super Global   ###
        $this->EE =& get_instance();

        //###   General Variables   ###
        $this->site_id = $this->EE->config->item('site_id');

//TO DO - need to check if inner function called, not assume construct.
        $this->return_data = $this->all();
    }//###   End of __construct function


    function Store_options() {

	} //###   End of Store_options function

    function all() {
        $data = array();
        $modifier = "";
        $showOption = true;
        $tagData = $this->EE->TMPL->tagdata;
        $stock = array();

        //###   Get entry_id parameter   ###
        $param = html_entity_decode($this->EE->TMPL->fetch_param('entry_id'));
        $entryID = preg_replace("/[^0-9\|]/", '', $param);
        if (empty($entryID))
            return $this->EE->TMPL->no_results();

        //###   Get modifier_only parameter   ###
        if( $param = $this->EE->TMPL->fetch_param('modifier') )
            $modifier = $this->EE->db->escape_str($param);

        //###   Get option_only parameter   ###
        if( $param = $this->EE->TMPL->fetch_param('option_only') )
            $showOption = $this->check_boolean($param);

//TO DO - $showModifier & $showOption (only) NOT YET IMPLEMENTED

        //###   Get limit parameter   ###
        $limit = preg_replace("/[^0-9]/", '', $this->EE->TMPL->fetch_param('limit'));

        $stockDB = $this->get_db_stock($entryID);

        //###   Build array of stock   ###
        foreach($stockDB as $option) {
            $stock[$option["sku"]] = array(
                "stock"         => $option["stock_level"],
                "minimum"       => $option["min_order_qty"]
            );
        }//###   End of foreach

        if (empty($modifier)) {
            $modifier = $this->get_db_modifiers($entryID);
        }

        $allModifiers = $this->get_db_modifiers();

        $optionsDB = $this->get_db_stock_options($entryID, $modifier);
        if (!empty($optionsDB))
            $data = $this->sort_options($optionsDB);
        if (empty($data))
            return $this->EE->TMPL->no_results();

        if (empty($tagData)) {
            return $this->single_tag($data);

        } else {
            $parseData = array();
            //###   Build EE array for output   ###
            foreach($data as $sku => $skuOptions) {
                //$skuData = array();
                //$skuData["options"] = $skuOptions;
                //$parseData[] = $skuData;
                $buildRow = array(
                    "sku"           => $sku,
                    "option_sku"    => $sku,
                    "stock"         => $stock[$sku]["stock"],
                    "stock_level"   => $stock[$sku]["stock"],
                    "minimum"       => $stock[$sku]["minimum"],
                    "min_order_qty" => $stock[$sku]["minimum"],
                    /*"options"       => $skuOptions,*/
                    "price"         => $skuOptions["price"],
                    "option_id"     => $skuOptions["opt_id"],
                    "opt_id"        => $skuOptions["opt_id"],
                    "modifier_id"   => $skuOptions["mod_id"],
                    "mod_id"        => $skuOptions["mod_id"],

                    "has_stock"     => ($stock[$sku]["stock"] > 0) ? 'TRUE' : 'FALSE',
                    "no_stock"      => ($stock[$sku]["stock"] <= 0) ? 'TRUE' : 'FALSE',
                    "has_minimum"   => (!empty($stock[$sku]["minimum"])) ? 'TRUE' : 'FALSE'
                );

                //###   Set default (empty) values for modifiers - so modifier variables are parsed   ###
                foreach ($allModifiers as $modName)
                    $buildRow[$modName] = "";

                //###   Single Modifier has "modifier" & "option" key values...   ###
                if (!empty($skuOptions["option"])) {
                    if (!empty($skuOptions["modifier"]))
                        $buildRow["label"] = $skuOptions["modifier"] . ": " . $skuOptions["option"];
                    else
                        $buildRow["label"] = $skuOptions["option"];

                    $buildRow["options"][] = array(
                        "modifier"  => $skuOptions["modifier"],
                        "option"    => $skuOptions["option"]);

                } else {
                    //###   Multiple Modifiers! Strip off all the known keys, leaving the modifiers
                    $tempArray = $skuOptions;
                    unset($tempArray["sku"]);
                    unset($tempArray["price"]);
                    unset($tempArray["opt_id"]);
                    unset($tempArray["mod_id"]);
                    foreach ($tempArray as $key => $value) {
                        $buildRow["options"][] = array(
                            "modifier"  => $key,
                            "option"    => $value);
                        $buildRow["label"] .= $value . " ";
                        $buildRow[$key] = $value;
                    }
                    $buildRow["label"] = substr($buildRow["label"], 0, -1);
                }







/*                if (!empty($modifier)) {
                    if (is_array($modifier)) {
                        foreach($modifier as $mod) {
                            $buildRow[$mod]     = $skuOptions[$mod];
                            $buildRow["label"]  = $skuOptions[$mod];
                        }
                    } else {
                        $buildRow[$modifier]    = $skuOptions[$modifier];
                        $buildRow["label"]      = $skuOptions[$modifier];
                    }

                } else {
                    $buildRow["label"]          = $skuOptions["option"];
                    $buildRow["option_name"]    = $skuOptions["option"];
                    $buildRow["modifier_name"]  = $skuOptions["modifier"];
                }*/

                $parseData[] = $buildRow;
            }
//echo "<pre>parseData ";var_dump($parseData);echo "</pre>";

            if (!empty($limit)) {
                $parseData = array_slice($parseData, 0, $limit);
            }
            $tagData = $this->EE->functions->prep_conditionals($tagData, $parseData);
            $output = $this->EE->TMPL->parse_variables($tagData, $parseData);
            return $output;
        }
    } //###   End of all function


    function total() {
        $instock = false;

        //###   Get entry_id parameter   ###
        $param = $this->EE->TMPL->fetch_param('entry_id');
        $entryID = preg_replace("/[^0-9\|]/", '', $param);
        if (empty($entryID))
            return 0;

        //###   Get modifier parameter   ###
        $modifier = "";
        if( $param = $this->EE->TMPL->fetch_param('modifier') )
            $modifier = $this->EE->db->escape_str($param);

        //###   Get option parameter   ###
        $option = "";
        if( $param = $this->EE->TMPL->fetch_param('option') )
            $option = $this->EE->db->escape_str($param);

        //###   Get option_only parameter   ###
        if( $param = $this->EE->TMPL->fetch_param('in_stock') )
            $instock = $this->check_boolean($param);

/*        $data = $this->get_db_stock_options($entryID);
        if (empty($data))
            return 0;
        else
            return count($data); */

        $this->EE->db->select("so.sku")
                     ->distinct()
                     ->from('exp_store_stock_options as so')
                     ->join('exp_store_product_options as po', 'po.product_opt_id = so.product_opt_id');

        if ($instock) {
            $this->EE->db->join('exp_store_stock as ss', 'so.sku = ss.sku');
        }

        $this->EE->db->where('so.entry_id', $entryID);

        if (!empty($option)) {
            $this->EE->db->where('po.opt_name', $option);
            if (substr($option, 0, 4) == 'not ') {
                $option = trim(substr($option, 3));
                $this->EE->db->where('po.opt_name !=', $option);
            } else {
                $this->EE->db->where('po.opt_name', $option);
            }
        }
        if (!empty($modifier)) {
            if (substr($modifier, 0, 4) == 'not ') {
                $modifier = trim(substr($modifier, 3));
                $this->EE->db->where('(SELECT DISTINCT pm.entry_id FROM exp_store_product_modifiers as pm WHERE pm.entry_id = '.$entryID.' AND pm.mod_name != "'.$modifier.'")');
            } else {
                $this->EE->db->where('(SELECT DISTINCT pm.entry_id FROM exp_store_product_modifiers as pm WHERE pm.entry_id = '.$entryID.' AND pm.mod_name = "'.$modifier.'")');
            }
        }
        if ($instock) {
            $this->EE->db->where('((ss.stock_level > 0 AND ss.track_stock = "y") OR (ss.track_stock != "y"))');
        }
        $query = $this->EE->db->get();

        $rtnVal = false;
        if (!empty($query))
            $rtnVal = $query->num_rows();

        return $rtnVal;

    } //###   End of total function


    private function single_tag($data) {
//TO DO
    } //###   End of single_tag function


    private function sort_options($optionsArray) {
        $data = array();
//        $showModifier = false;
//        $showOption = true;

        if (!is_array($optionsArray))
            return false;

        //###   Build array of options   ###
        foreach($optionsArray as $option) {
/*            $text = "";
            if ($showModifier) {
                $text .= $option["modifier"];
                if ($showOption)
                    $text .= " ";
            }
            if ($showOption) {
                $text .= $option["option"];
            }*/
            $data[$option["sku"]] = $option;
        }//###   End of foreach

        return $data;
    } //###   End of sort_options function



    private function get_db_stock($entry_id) {
        //###   Get the stock for supplied Entry ID
        $query = $this->EE->db->select()
               ->from('exp_store_stock')
               ->where('entry_id', $entry_id)
               ->get();

        if ($query->num_rows() > 0)
            $rtnVal = $query->result_array();
        else
            $rtnVal = false;
        return $rtnVal;
    } //###   End of get_db_stock function


    private function get_db_modifiers($entry_id = 0) {
        $rtnArray = array();

        $this->EE->db->select("pm.mod_name")
                     ->distinct()
                     ->from('exp_store_product_modifiers as pm');
        if (!empty($entry_id))
            $this->EE->db->where('pm.entry_id', $entry_id);
        $query = $this->EE->db->get();

        if ($query->num_rows() > 0) {
            $rtnArray = $query->result_array();
            //###   Clean return array   ###
            foreach ($rtnArray as $value)
                $rtnVal[] = $value["mod_name"];

        } else {
            $rtnVal = false;
        }

        return $rtnVal;
    } //###   End of get_db_modifiers function


    private function get_db_stock_options($entry_id, $modifier = "") {
    	//###   Get the stock for supplied Entry ID
        if (is_array($modifier)) {
            $sql = "SELECT ".$modifier[0].".sku as 'sku', ".$modifier[0].".opt_price_mod as 'price', ".$modifier[0].".product_opt_id as 'opt_id', ".$modifier[0].".product_mod_id as 'mod_id'";
            foreach ($modifier as $mod)
                $sql .= ", ".$mod.".opt_name as '".$mod."'";
            $sql .= " FROM ";
            foreach ($modifier as $mod) {
                $sql .= " (SELECT so.sku, po.opt_name, po.opt_price_mod, po.product_opt_id, po.product_mod_id, po.opt_order
                        FROM exp_store_stock_options as so
                        JOIN exp_store_product_modifiers as pm ON so.product_mod_id = pm.product_mod_id
                        JOIN exp_store_product_options as po ON po.product_opt_id = so.product_opt_id
                        WHERE so.entry_id = ".$entry_id." AND pm.mod_name = '".$mod."'
                        ORDER BY po.opt_order, po.opt_name ASC) as ".$mod.",";
            }

            //###   Remove trailing comma   ###
            $sql = substr($sql, 0, -1);

            if (count($modifier) > 1) {
                $sql .= " WHERE ";
                foreach ($modifier as $key => $mod) {
                    if ($key != 0)
                        $sql .= $modifier[0].".sku = ".$mod.".sku ";
                }
            }

            $sql .= " ORDER BY ".$modifier[0].".opt_order ASC";

            $query = $this->EE->db->query($sql);

        } else {
            //###   Either no modifier, or single modifier   ###
/*            if (empty($modifier)) {
                $this->EE->db->select("so.sku");
            }*/
            $this->EE->db
                   ->select("so.sku, pm.mod_name as 'modifier', po.opt_name as 'option', po.opt_price_mod as 'price', po.product_opt_id as 'opt_id', po.product_mod_id as 'mod_id'")
                   ->distinct()
                   ->from('exp_store_stock_options as so')
                   ->join('exp_store_product_modifiers as pm', 'so.product_mod_id = pm.product_mod_id')
                   ->join('exp_store_product_options as po', 'po.product_opt_id = so.product_opt_id')
                   ->where('so.entry_id', $entry_id);
            if (!empty($modifier))
                $this->EE->db->where('pm.mod_name', $modifier);
            $this->EE->db->order_by('po.opt_order, po.opt_name, modifier ASC');
            $query = $this->EE->db->get();
        }

        $rtnVal = false;
        if (!empty($query)) {
            if ($query->num_rows() > 0)
                $rtnVal = $query->result_array();
        }

//echo "<pre>sql ";var_dump($rtnVal);echo "</pre>";

        return $rtnVal;
    } //###   End of get_db_stock_options function





	private function check_boolean($var = false, $check = true) {
		$returnVal = false;
		if ($check !== false)
			$check = true;

		if (empty($var)) {
			//###   No variable has been passed or is empty, so will return default (false), unless checking for false   ###
			if (!$check)
				$returnVal = true;

		} else {
			if ($check) {
				if (strtolower($var) === "true" || strtolower($var) === "t" || strtolower($var) === "yes" || strtolower($var) === "y" || $var === "1")
					$returnVal = true;
			} else {
				if (strtolower($var) === "false" || strtolower($var) === "f" || strtolower($var) === "no" || strtolower($var) === "n" || $var === "0")
					$returnVal = true;
            }
		}

		return $returnVal;
	} //###   End of check_boolean function


// ----------------------------------------
//  Plugin Usage
// ----------------------------------------
// This function describes how the plugin is used.
//  Make sure and use output buffering

function usage() {
	ob_start();
?>



Support and more help can be found here:

Primary tag: {exp:store_options entry_id="{entry_id}"}

Single variables:
{options_sku} = SKU for the individual option
{stock} (or {stock_level}) = stock level for the option
{minimum} (or {min_order_qty}) = Minimum order quantity for this option
                           => $skuOptions,

Variable Pairs:
{options} ...
    {label} = Option label
    {modifier} = modifier name for this option
    {option} =
{/options}

Conditionals:
{if has_stock} = This option currently has stock
{if no_stock}  = This option currently has NO stock
{if has_minimum} = This item requires a minimum quantity to purchase
    Example: {if has_minimum} Minimum order: {minimum}{/if}


{exp:store_options:total entry_id="{entry_id}" parse="inward"}


	<?php
	$buffer = ob_get_contents();

	ob_end_clean();

	return $buffer;
} /* ###   End of usage() Function   ### */

}  /* ###   End of Class   ### */