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
	const ELEMENT_KEY_URL = "URL";
	const ELEMENT_KEY_IMAGE_URL = "IMGURL";
	const ELEMENT_KEY_BASEPRICE_VAT = "BASEPRICE_VAT";
	const ELEMENT_KEY_SALEPRICE_VAT = "PRICE_VAT";

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

	function buildDefaultOptions($options=[]) {

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
			"hostname" => null,
			"image_geometry" => "800x800",
			"image_watermark" => null,
		];
	}

	function getObjectIds($options=[]) {
		$options += [
			"offset" => 0,
			"limit" => 100,
			"exclude_tag" => \Tag::FindFirst("code","exclude_from_xml"),
		];
		$ids = $this->dbmole->selectIntoArray("SELECT distinct(id) FROM cards
			WHERE
			id NOT IN (SELECT cards.id FROM cards,card_tags WHERE card_tags.card_id=cards.id AND card_tags.id=:exclude_tag_id) AND
			deleted='f' AND
			visible='t'
			ORDER BY id
			LIMIT :limit OFFSET :offset", [
				":offset" => $options["offset"],
				":limit" => $options["limit"],
				":exclude_tag_id" => $options["exclude_tag"],
			]);
		return $ids;
	}

	function getObjects($options=[]) {
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
		$_product_url = $this->_buildCardLink($card, $options);

		foreach($card->getProducts($card_options) as $p) {
			$p_ar = $this->itemToArray($p);
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
			$path = $c->getNamePath($this->lang, array("glue" => " ${_cat_glue} ", "start_level" => 1));
			$categories[] = $path;
		}
		// Element CATEGORYTEXT se vyskytuje více než jednou -> Heureka
		// zpracovává pouze první výskyt CATEGORYTEXT u každé položky.
		$categories = array_slice($categories, 0, 1);
		return $categories;
	}

	protected function prepareDescription($card) {
		$_description = $this->markdown->transform($card->getTeaser($this->lang));;
		$_description = preg_replace('/[\x{feff}]/u', "", $_description);
		return strip_tags($_description);
	}

	/**
	 * Vyplneni atributu spolecnych pro vsechny vyhledavace.
	 */
	function itemToArray($product) {
		$_image = $product->getImage();

		$item_attrs = array();

		$amount = 1;
		$_unit = $product->getUnit()->getUnit();
		($_unit=="cm") && ($amount=100);
		$_product_price = $this->price_finder->getPrice($product, $amount);

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

		$item_attrs[static::ELEMENT_KEY_ITEM_ID] = $product->getId();
		$item_attrs[static::ELEMENT_KEY_EAN] = $product_ean;
		$item_attrs[static::ELEMENT_KEY_MPN] = null;

		$item_attrs[static::ELEMENT_KEY_PRODUCT_NAME] = $product_name;
		$_image && ($item_attrs[static::ELEMENT_KEY_IMAGE_URL] = $_image);

		$_currency = $this->price_finder->getCurrency();

		$_price_with_currency = $this->options["price_with_currency"];

		# zakladni cena pred slevou
		$_product_price && ($item_attrs[static::ELEMENT_KEY_BASEPRICE_VAT] = number_format(round($_product_price->getPriceBeforeDiscountInclVat(),$_currency->getDecimalsSummary()),$_currency->getDecimalsSummary(),".",""));
		$_product_price && ($_price_with_currency===true) && ($item_attrs[static::ELEMENT_KEY_BASEPRICE_VAT] .= sprintf(" %s",$_currency->getCode()));

		# aktualni, konecna cena
		$_product_price && ($item_attrs[static::ELEMENT_KEY_SALEPRICE_VAT] = number_format(round($_product_price->getPriceInclVat(),$_currency->getDecimalsSummary()),$_currency->getDecimalsSummary(),".",""));
		$_product_price && ($_price_with_currency===true) && ($item_attrs[static::ELEMENT_KEY_SALEPRICE_VAT] .= sprintf(" %s",$_currency->getCode()));

		/*
		 * pokud je cena zlevnena,
		 * - doplnime do patricneho klice ponizenou cenu
		 * - doplnime platnost slevy do klice 'sale_price_effective_date'
		 */
		if ($_product_price && $_product_price->discounted()) {
			#				$sale_price_effective_date_from = $_product_price->discountedFrom();
			#				$sale_price_effective_date_to = $_product_price->discountedTo();
			$sale_price_effective_date_from = null;
			$sale_price_effective_date_to = null;
			$sale_price_effective_date = [
				$sale_price_effective_date_from ? date("c", strtotime($sale_price_effective_date_from)) : null,
				$sale_price_effective_date_to ? date("c", strtotime($sale_price_effective_date_to)) : null,
			];
			# potrebujeme mit oba datumy, abychom platnost dali do feedu
			if (sizeof(array_filter($sale_price_effective_date))===2) {
				$item_attrs["SALE_PRICE_EFFECTIVE_DATE"] = join("/", $sale_price_effective_date);
			}
		}

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
