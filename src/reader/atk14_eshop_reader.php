<?php

namespace ProductFeedGenerator\Reader;

class Atk14EshopReader {

	const ELEMENT_KEY_CATEGORIES = "CATEGORYTEXT";
	const ELEMENT_KEY_DESCRIPTION = "DESCRIPTION";
	const ELEMENT_KEY_MANUFACTURER = "MANUFACTURER";
	const ELEMENT_KEY_PRODUCT_NAME = "PRODUCTNAME";
	const ELEMENT_KEY_EAN = "EAN";
	# Manufacturer Part Number - zatim nepouzivame, posilame null
	const ELEMENT_KEY_MPN = "MPN";
	const ELEMENT_KEY_GROUP_ID = "ITEMGROUP_ID";
	const ELEMENT_KEY_ITEM_ID = "ITEM_ID";
	const ELEMENT_KEY_CATALOG_ID = "CATALOG_ID";
	const ELEMENT_KEY_URL = "URL";
	const ELEMENT_KEY_IMAGE_URL = "IMGURL";
	const ELEMENT_KEY_BASEPRICE_VAT = "BASEPRICE_VAT";
	const ELEMENT_KEY_SALEPRICE_VAT = "PRICE_VAT";

	const ELEMENT_KEY_UNIT = "UNIT";
	const ELEMENT_KEY_UNIT_PRICING_BASE_MEASURE = "UNIT_PRICING_BASE_MEASURE";
	const ELEMENT_KEY_UNIT_PRICING_MEASURE = "UNIT_PRICING_MEASURE";
	const ELEMENT_KEY_STOCKCOUNT = "STOCKCOUNT";
	const ELEMENT_KEY_AVAILABILITY = "CAN_BE_ORDERED";

	const ELEMENT_KEY_SALE_PRICE_START_DATE = "SALE_PRICE_START_DATE";
	const ELEMENT_KEY_SALE_PRICE_END_DATE = "SALE_PRICE_END_DATE";
	const ELEMENT_KEY_PRICE_IS_DISCOUNTED = "PRICE_IS_DISCOUNTED";

	function __construct($constructor_options=[]) {
		$this->markdown = $this->_getMarkdown();
		$this->dbmole = \PgMole::GetInstance();

		$this->buildDefaultOptions();
		$this->generator_options = [];
		$this->constructor_options = $constructor_options;
		$this->live_options = [];
		$this->setOptions();
	}


	function setGeneratorOptions($generator_options=[]) {
		$this->generator_options = $generator_options;
		$this->setOptions( );
	}

	/**
	 * Options muzou prijit z ruznych mist.
	 * Uvazuji se v poradi:
	 * - vychozi options platne pro Atk14EshopReader
	 * - options prichazejici z generatoru
	 * - options prichazejici z konstruktoru Atk14EshopReader
	 * - options predane v setOptions
	 *
	 * @toto tak toto poradne otestovat!!!
	 */
	function setOptions($options=[]) {
		$this->live_options = array_merge($this->live_options, $options);

		$options = array_merge( $this->default_options, $this->generator_options, $this->constructor_options, $this->live_options);

		$this->lang = $options["lang"];
		$this->price_finder = $options["price_finder"];

		$this->options = $options;
	}

	protected function buildDefaultOptions($options=[]) {

		$_price_finder_options = [];
		if (isset($options["currency"])) {
			$_price_finder_options["currency"] = $options["currency"];
		}
		$_default_price_finder = $this->_getDefaultPriceFinder($_price_finder_options);

		$this->default_options = [
			"price_with_currency" => false,
			"price_finder" => $_default_price_finder,
			"lang" => null,
			"category_path_connector" => ">",
			"merge_multiple_categories" => false,
			"hostname" => null,
			"image_geometry" => "800x800",
			"image_watermark" => null,
			"region" => "CR",
			"categories_limit" => 1,
		];
	}

	protected $cachedIds = null;

	protected $idsToIgnore = null;

	/**
	 * This method can call a query to find object which should not be output.
	 *
	 * @return Card|int[]
	 */
	function getObjectIdsToIgnore() {
		return null;
	}

	function getObjectIds($options=[]) {
		$options += [
			"offset" => 0,
			"limit" => 100,
			"exclude_tag" => \Tag::FindFirst("code","exclude_from_xml"),
		];
		if (is_null($this->cachedIds)) {
			$conditions = [
				"id NOT IN (SELECT cards.id FROM cards,card_tags WHERE card_tags.card_id=cards.id AND card_tags.id=:exclude_tag_id)",
				"deleted='f'",
				"visible='t'",
			];
			$bindAr = [
				":offset" => $options["offset"],
				":limit" => $options["limit"],
				":exclude_tag_id" => $options["exclude_tag"],
			];
			$this->idsToIgnore = $this->getObjectIdsToIgnore();
			if (!is_null($this->idsToIgnore)) {
				$conditions[] = "id NOT IN :ids_to_ignore";
				$bindAr[":ids_to_ignore"] = $this->idsToIgnore;
			}
			$conditions = join(" AND ", array_map(function($x) {
				return "({$x})";
			}, $conditions));
			$this->cachedIds = $this->dbmole->selectIntoArray("SELECT distinct(id) FROM cards
				WHERE {$conditions}
				ORDER BY id
				LIMIT :limit OFFSET :offset", $bindAr);
		}
		$ids = array_slice($this->cachedIds,$options["offset"],$options["limit"]);

		\Cache::Clear("Card");
		\Cache::Prepare("Card",$ids);

		return $ids;
	}

	function getObjects($options=[]) {
		\Cache::Clear();
		return \Card::GetInstanceById($this->getObjectIds($options));
	}

	function objectToArray($card,$options=array()) {
		$options += [];
		$products_ar = [];

		$card_options = array(
			"force_read" => true,
		);

		$_brand = $card->getBrand();
		$_brand = "$_brand";
		$_categories = $this->prepareCategories($card);
		$_description = $this->prepareDescription($card);
		$_card_id = $card->getId();
		$_card_name = $card->getName($this->lang);
		$_product_url = $this->_buildCardLink($card, $options);

		foreach($card->getProducts($card_options) as $p) {
			$p_ar = $this->itemToArray($p);
			if (!$p_ar) {
				continue;
			}
			if (!$card->hasVariants()) {
			$p_ar[static::ELEMENT_KEY_PRODUCT_NAME] = $_card_name;
			}
			$p_ar[static::ELEMENT_KEY_CATEGORIES] = $_categories;
			$p_ar[static::ELEMENT_KEY_DESCRIPTION] = $_description;
			$p_ar[static::ELEMENT_KEY_GROUP_ID] = $_card_id;
			$p_ar[static::ELEMENT_KEY_URL] = $_product_url;
			$_brand && ($p_ar[static::ELEMENT_KEY_MANUFACTURER] = $_brand);
			$products_ar[] = $p_ar;
		}

		return $products_ar;
	}

	protected function prepareCategories($card) {
		$categories = array();
		foreach($card->getCategories() as $c) {
			$parent = $c->getParentCategory();
			if ($c->isFilter() || !$c->isVisible() || ($parent && $parent->isFilter()) ) {
				continue;
			}
			$_cat_glue = $this->options["category_path_connector"];
			$path = $c->getNamePath($this->lang, array("glue" => " {$_cat_glue} ", "start_level" => 1));
			$path = str_replace(",", "", $path);
			$categories[] = $path;
		}
		// Element CATEGORYTEXT se vyskytuje více než jednou -> Heureka
		// zpracovává pouze první výskyt CATEGORYTEXT u každé položky.
		// https://forum.mergado.cz/t/element-categorytext/2770
		$categories_limit = $this->options["categories_limit"];
		if (!is_null($categories_limit)) {
			$categories = array_slice($categories, 0, (int)$categories_limit);
		}
		if ($this->options["merge_multiple_categories"]===true) {
			$categories = [implode(", ", $categories)];
		}
		return $categories;
	}

	protected function prepareProductPriceData(\Product $object, &$item_data) {
		$_unit = $object->getUnit();
		$_currency = $this->price_finder->getCurrency();

		$_product_price = $this->price_finder->getPrice($object, (int)$_unit->getDisplayUnitMultiplier());

		$_price_with_currency = $this->options["price_with_currency"];

		# zakladni cena pred slevou
		$_product_price && ($item_data[static::ELEMENT_KEY_BASEPRICE_VAT] = number_format(round($_product_price->getPriceBeforeDiscountInclVat(),$_currency->getDecimalsSummary()),$_currency->getDecimalsSummary(),".",""));
		$_product_price && ($_price_with_currency===true) && ($item_data[static::ELEMENT_KEY_BASEPRICE_VAT] .= sprintf(" %s",$_currency->getCode()));

		# aktualni, konecna cena
		$_product_price && ($item_data[static::ELEMENT_KEY_SALEPRICE_VAT] = number_format(round($_product_price->getPriceInclVat(),$_currency->getDecimalsSummary()),$_currency->getDecimalsSummary(),".",""));
		$_product_price && ($_price_with_currency===true) && ($item_data[static::ELEMENT_KEY_SALEPRICE_VAT] .= sprintf(" %s",$_currency->getCode()));

		$_product_price && $item_data[static::ELEMENT_KEY_PRICE_IS_DISCOUNTED] = $_product_price->discounted();
		$item_data[static::ELEMENT_KEY_SALE_PRICE_START_DATE] = null;
		$item_data[static::ELEMENT_KEY_SALE_PRICE_END_DATE] = null;
		/*
		 * pokud je cena zlevnena,
		 * - doplnime do patricneho klice ponizenou cenu
		 * - doplnime platnost slevy do klice 'sale_price_effective_date'
		 */
		if ($_product_price && $_product_price->discounted()) {
			$item_data[static::ELEMENT_KEY_SALE_PRICE_START_DATE] = $_product_price->discountedFrom();
			$item_data[static::ELEMENT_KEY_SALE_PRICE_END_DATE] = $_product_price->discountedTo();
		}
	}

	protected function prepareDescription($card) {
		$_description = $this->markdown->transform($card->getTeaser($this->lang));;
		$_description = preg_replace('/[\x{feff}]/u', "", $_description);
		return strip_tags($_description);
	}

	/**
	 * Vyplneni atributu spolecnych pro vsechny vyhledavace.
	 */
	function itemToArray(\Product $product) {
		if (is_null($this->price_finder->getPrice($product))) {
			return null;
		}
		$_image = $product->getImage();
		$_unit = $product->getUnit();

		$item_attrs = array();

		$item_attrs[static::ELEMENT_KEY_UNIT] = $_unit->getUnit();
		$item_attrs[static::ELEMENT_KEY_UNIT_PRICING_BASE_MEASURE] = sprintf("%s %s", "1", $_unit->getDisplayUnit());
		$item_attrs[static::ELEMENT_KEY_STOCKCOUNT] = $product->getStockcount();
		$_region = \Region::GetRegionByCode($this->options["region"]);
		$item_attrs[static::ELEMENT_KEY_AVAILABILITY] = $product->canBeOrdered(["region" => $_region]);

		$this->prepareProductPriceData($product, $item_attrs);

		$product_name = $product->getName($this->lang);
		$product_ean = $product->hasKey("ean") ? $product->g("ean") : null;

		if ($_image) {
			$_url_options = [
				$this->options["image_geometry"],
				$this->options["image_watermark"],
			];
			$_url_options = array_filter($_url_options);
			$_url_options = join(",", $_url_options);
			$_ppq = new \Pupiq($_image->getUrl());
			$_image = $_ppq->getUrl($_url_options);
		}

		$item_attrs[static::ELEMENT_KEY_CATALOG_ID] = $product->getCatalogId();
		$item_attrs[static::ELEMENT_KEY_ITEM_ID] = $product->getId();
		$item_attrs[static::ELEMENT_KEY_EAN] = $product_ean;
		$item_attrs[static::ELEMENT_KEY_MPN] = null;

		$item_attrs[static::ELEMENT_KEY_PRODUCT_NAME] = $product_name;
		$_image && ($item_attrs[static::ELEMENT_KEY_IMAGE_URL] = $_image);

		return $item_attrs;
	}

	function _buildCardLink($card, $options=[]) {
		$options += array(
			"additional_url_params" => [
#				"utm_source" => "google",
#				"utm_medium" => "cpc",
#				"utm_campaign" => "shopping",
			],
		);

		$_url_params = array(
			"controller" => "cards",
			"action" => "detail",
			"id" => $card,
			"lang" => $this->lang,
		);
		$_url_params += $options["additional_url_params"];
		$_url_options = array(
			"with_hostname" => true,
			"connector" => "&",
			"ssl" => true,
		);

		if ($_hostname = $this->options["hostname"]) {
			$_url_options["with_hostname"] = $_hostname;
		}
		return  \Atk14Url::BuildLink($_url_params, $_url_options);

	}

	function _getMarkdown() {
		return new \DrinkMarkdown(array(
			"table_class" => "table",
			"html_purification_enabled" => false,
			"iobjects_processing_enabled" => true,
			"urlize_text" => true,
		));
	}

	function _getDefaultPriceFinder($options=array()) {
		$options += array(
			"currency" => null,
		);
		return \PriceFinder::GetInstance(null, \Currency::FindByCode($options["currency"]));
	}
}
