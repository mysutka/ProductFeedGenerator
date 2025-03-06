# Product Feed Generator

This package provides library which helps create XML and CSV feeds for various price comparison services, e.g. Google Shopping, Heureka, etc.
The library is intended to be extensible.
A developer can create own generator for other price comparison services and he can also create own reader if he for example needs to alter selection of objects or work with different objects.

First you can see examples in `src/robots/product_feed_generator_robot.php`

Generators available for these price comparators

- Heureka.cz
- Zbozi.cz
- Google Shopping
- Google Merchants

## Base classes
Two basic classes are used which can be extended
**Atk14EshopReader** selects, reads objects (usually products) and converts them to array
**FeedGenerator** works with arrays returned by Atk14EshopReader. It should not be used directly. New class should be created which inherits FeedGenerator

### Atk14EshopReader options
- price_with_currency - boolean [default: false]
- price_finder
- lang - string [default: null => application default] - forces the reader to use object translations for specified language when the object supports it
- category_path_connector - string [default: '>']
- hostname - string [default: ATK14_HTTP_HOST]
- image_geometry - string [default: '800x800']
- image_watermark - string [default: null => no watermark is used]

### FeedGenerator options
- logger
- output_format
- xml_item_element_name
- feed_begin
- feed_end
- fixed_values

### Examples
Usually only the `FeedGenerator` class can be used:
```php
$generator = new \ProductFeedGenerator\Generator\GoogleShoppingGenerator();
$generator->exportTo("/path/to/public/web/directory/product_feeds/google_shopping.xml")
```
This will output xml element DELIVERY_DATE containing text '25'. It will override original value if it should be generated by the generator.
```php
$generator = new \ProductFeedGenerator\Generator\HeurekaCzGenerator(null, [
  "fixed_values" => [
    "DELIVERY_DATE" => 25,
]);
```
Output
```xml
<DELIVERY_DATE>25</DELIVERY_DATE>
```
When needed elements that are normally in output can be removed. In this example generator by default outputs element EAN in each item. By specifying null for EAN in fixed_values option, the element will disappear from output.
```php
$generator = new \ProductFeedGenerator\Generator\HeurekaCzGenerator(null, [
  "fixed_values" => [
    "EAN" => null,
]);
```

Creating feed with prices in another currency, EUR for example
```php
$eur_price_finder = PriceFinder::GetInstance(null, Currency::FindByCode("EUR"));
$generator = new \ProductFeedGenerator\Generator\HeurekaCzGenerator(null, [
  "price_finder" => $eur_price_finder,
]);
```
## Custom Reader

Most common example is a reader which generates a feed for a limited set of products. In this case we create a new class as a descendant of `ProductFeedGenerator\Reader\Atk14EshopReader` which consists of a single method getObjectIds(). 
This LimitedEshopReader provides ids of products created during the last month.
```php
class LimitedEshopReader extends ProductFeedGenerator\Reader\Atk14EshopReader {

  function getObjectIds($options=[]) {
    $options += [ 
      "offset" => 0,
    ];
    $ids = $this->dbmole->selectIntoArray("SELECT distinct(id) FROM cards
      WHERE
        created_at>now() - interval '1 month' AND
        NOT deleted AND
        visible
      ORDER BY id
      LIMIT 100 OFFSET :offset", array(
        ":offset" => $options["offset"],
      ));
    return $ids;
  }
}
```

## Custom Generator

Sometimes happenes that we need to send the comparison service a value that is a bit altered while the generator provides it as it is stored in the database. So let's create our custom Generator.
Let's say our standard GoogleShoppingGenerator does not provide "g:availability" attribute (which in actually does) but there is attribute STOCKCOUNT provided by the main `Atk14EshopReader` class. So we create new Generator class which adds the STOCKCOUNT attribute to the values from the Reader class and then adds the "g:availability" attribute to the output.
```php
class CustomGoogleShoppingGenerator extends ProductFeedGenerator\Generator\GoogleShoppingGenerator {
  function getAttributesMap() {
    return parent::getAttributesMap() + [
      "g:availability" => "STOCKCOUNT",
    ];
  }

  function afterFilter($values) {
    $values["g:availability"] = ($values["g:availability"]) > 0 ? "in stock" : "out of stock";
    return $values;
  }
}
```
The method `getAttributesMap()` is called in the parent Generator class. Here, we override it and add the `g:availability` attribute by using the general `STOCKCOUNT` attribute provided by the Reader class which is simply a number of products in our warehouse.
Then we add the `afterFilter()` method which is also called by the parent class FeedGenerator. It is used to transform the values which would be normally sent to final output. In our method we modify the `g:availability` attribute to our needs, which means we send a text "in stock" or "out of stock" to the output instead of exact number.