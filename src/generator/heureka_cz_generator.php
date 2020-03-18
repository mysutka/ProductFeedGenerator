<?php
namespace ProductFeedGenerator\Generator;
use ProductFeedGenerator\FeedGenerator;

class HeurekaCzGenerator extends FeedGenerator {

	function __construct($reader, $options=[]) {
		$options += [
			"xml_item_element_name" => "SHOPITEM",

			"feed_begin" => "<SHOP>",
			"feed_end" => "</SHOP>",

			"category_path_connector" => "|",
			"fixed_values" => [
				"DELIVERY_DATE" => 6,
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
		];
	}
}
