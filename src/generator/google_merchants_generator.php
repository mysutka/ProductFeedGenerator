<?php
namespace ProductFeedGenerator\Generator;
use ProductFeedGenerator\FeedGenerator;

class GoogleMerchantsGenerator extends FeedGenerator {

	function __construct($reader, $options=[]) {
		$options += [
			"price_with_currency" => true,
			"category_path_connector" => ">",
			"output_format" => "csv",
#			"xml_item_element_name" => "entry",
		];
		return parent::__construct($reader, $options);
	}

	function getAttributesMap() {
		return [
			"ID" => "ITEM_ID",
			"Item_description" => "DESCRIPTION",
			"Image_URL" => "IMGURL",
			"Item_category" => "CATEGORYTEXT",
			"Final_URL" => "URL",
			"Item_title" => "PRODUCTNAME",
			"Price" => "PRICE_VAT",
		];
	}
}
