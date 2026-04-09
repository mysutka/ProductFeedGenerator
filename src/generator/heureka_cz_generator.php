<?php
namespace ProductFeedGenerator\Generator;
use ProductFeedGenerator\FeedGenerator;

use ProductFeedGenerator\Reader\Atk14EshopReader;

/**
 * This class is used to prepare structure to generate a feed for Heureka.cz.
 *
 * @see https://sluzby.heureka.cz/napoveda/xml-feed/ XML feed specification
 */
class HeurekaCzGenerator extends FeedGenerator {

	function __construct($reader, $options=[]) {
		$options += [
			"xml_item_element_name" => "SHOPITEM",

			"feed_begin" => "<SHOP>",
			"feed_end" => "</SHOP>",

			"category_path_connector" => "|",
			"fixed_values" => [
			],
		];
		return parent::__construct($reader, $options);
	}

	function getAttributesMap(): array {
		return [
			"CATEGORYTEXT" => "CATEGORYTEXT",
			"DESCRIPTION" => "DESCRIPTION",
			"EAN" => "EAN",
			"IMGURL" => "IMGURL",
			"ITEM_ID" => "ITEM_ID",
			"PRODUCTNAME" => "PRODUCTNAME",
			# extended product_name (@todo do Atk14EshopReader doplnime atribut)
			"PRODUCT" => "PRODUCTNAME",
			"URL" => "URL",
			"MANUFACTURER" => "MANUFACTURER",
			"ITEMGROUP_ID" => "ITEMGROUP_ID",

			"PRICE_VAT" => "PRICE_VAT",

			# only required to calculate DELIVERY_DATE and to be disposed in afterFilter
			"STOCKCOUNT" => "STOCKCOUNT",

		];
	}

	function afterFilter($values) {
		// DELIVERY_DATE: 0 = in stock, empty = not available.
		// Custom value can be passed via fixed_values["DELIVERY_DATE"] from the robot.
		if (!isset($this->options["fixed_values"]["DELIVERY_DATE"])) {
			if ($values[Atk14EshopReader::ELEMENT_KEY_STOCKCOUNT]===0) {
				$values["DELIVERY_DATE"] = "";
			} else {
				$values["DELIVERY_DATE"] = "0";
			}
		}
		unset($values[Atk14EshopReader::ELEMENT_KEY_STOCKCOUNT]);
		return $values;
	}

}
