# Product Feed Generator

This package provides library which helps create XML and CSV feeds for various price comparison services, e.g. Google Shopping, Heureka, etc.

For now see usafe examples in `src/robots/product_feed_generator_robot.php`

Generators available for these price comparators

- Heureka.cz
- Zbozi.cz
- Google Shopping
- Google Merchants

The library is intended to be extensible.
A developer can create own generator for other price comparison services and he can also create own reader if he for example needs to alter selection of objects or work with different objects.

Atk14EshopReader reads objects (usually products) and converts them to array

FeedGenerator works with arrays returned by Atk14EshopReader

## Custom Reader

Most common example is a reader which generates a feed for a limited set of products. In this case we create a new class as a descendant of `ProductFeedGenerator\Reader\Atk14EshopReader` which consists of a single method getObjectIds(). 
This LimitedEshopReader provides ids of products created during the last month.
```
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
