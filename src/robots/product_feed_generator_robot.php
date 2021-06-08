<?php
/**
 * Basic examples how to generate product feed for most common price comparators.
 */
class ProductFeedGeneratorRobot extends ApplicationRobot {

	function run() {
		global $ATK14_GLOBAL;
		global $argv;

		$reader = null;
		array_shift($argv);
		array_shift($argv);

		$todo_feeds = [];

		$known_feeds = [
			"heureka_cz", "heureka_sk", "zbozi_cz", "google_shopping", "google_merchants",
		];
		while($prm = array_shift($argv)) {
			$todo_feeds[] = $prm;
		}

		if ($todo_feeds && !array_intersect($known_feeds, $todo_feeds)) {
			$this->logger->info(sprintf("no known feeds given: %s", join(", ", $todo_feeds)));
			return;
		}

		if ($todo_feeds && ($unknown_feeds = array_diff($todo_feeds, $known_feeds))) {
			$this->logger->info(sprintf("some feeds will not be generated, unknown types: %s", join(", ", $unknown_feeds)));
		}

		$this->logger->flush();
		if (!$todo_feeds) {
			$todo_feeds = $known_feeds;
		}

		if (in_array("heureka_cz", $todo_feeds)) {
		# Create XML product feed for Heureka.cz price comparator
			$generator = new \ProductFeedGenerator\Generator\HeurekaCzGenerator($reader, [
				"logger" => $this->logger,
			]);
			$generator->exportTo($ATK14_GLOBAL->getPublicRoot()."product_feeds/heureka_cz.xml");
		}

		# Create XML product feed for Google Shopping price comparator
		# As we want the feed to contain prices in EUR, we will use specific PriceFinder
		if (in_array("google_shopping", $todo_feeds)) {
			$generator = new \ProductFeedGenerator\Generator\GoogleShoppingGenerator($reader, [
				"logger" => $this->logger,
				"price_finder" => $this->_getPriceFinder(["currency" => "EUR"]),
			]);
			$generator->exportTo($ATK14_GLOBAL->getPublicRoot()."product_feeds/google_shopping.xml");
		}

		# Create CSV product feed for Google Merchants
		# The output format is specified inside the GoogleMerchantsGenerator so it is not needed to put it as a parameter
		if (in_array("google_merchants", $todo_feeds)) {
			$generator = new \ProductFeedGenerator\Generator\GoogleMerchantsGenerator($reader, [
				"logger" => $this->logger,
				"output_format" => "csv",
			]);
			$generator->exportTo($ATK14_GLOBAL->getPublicRoot()."product_feeds/google_merchants.csv");
		}

		# Another example of product feed with some more parameters
		# - lang - slovak product translations will be used to create the feed
		# - hostname - under some conditions might be useful to generate links to products with different hostname
		# - eshop_url - url of the site
		# - feed_title - short description of the eshop. some price comparators use it, some don't.
		$generator = new \ProductFeedGenerator\Generator\GoogleShoppingGenerator($reader, [
			"logger" => $this->logger,
			"lang" => "sk",
			"feed_title" => "ukážkový obchod",
			"hostname" => "ukazkovy-eshop.sk.gibona.com",
			"eshop_url" => "ukazkovy-eshop.gibona.com",
		]);
		$generator->exportTo($ATK14_GLOBAL->getPublicRoot()."/product_feeds/google_shopping_sk.xml");

		return;
	}

	function _getPriceFinder($options=[]) {
		$options += [
			# default currency
			"currency" => null,
		];
		return PriceFinder::GetInstance(null, Currency::FindByCode($options["currency"]));
	}
}
