<?php
namespace ProductFeedGenerator\Generator;
use ProductFeedGenerator\FeedGenerator;

use ProductFeedGenerator\Reader\Atk14EshopReader;

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

	function getAttributesMap() {
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
		// DELIVERY_DATE is based on value in STOCKCOUNT
		// if the product is on stock we fill 0, otherwise empty value
		// @TODO other values can be used depending on number of days from order acceptance to delivery
		// we do not override value given via fixed_values parameter
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
