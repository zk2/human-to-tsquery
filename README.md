zk2\human-to-tsquery
================

[![Build Status](https://travis-ci.com/zk2/human-to-tsquery.svg?branch=master)](https://travis-ci.org/zk2/human-to-tsquery)

[![Latest Stable Version](https://poser.pugx.org/zk2/human-to-tsquery/v/stable)](https://packagist.org/packages/zk2/human-to-tsquery)
[![Total Downloads](https://poser.pugx.org/zk2/human-to-tsquery/downloads)](https://packagist.org/packages/zk2/human-to-tsquery)
[![Latest Unstable Version](https://poser.pugx.org/zk2/human-to-tsquery/v/unstable)](https://packagist.org/packages/zk2/human-to-tsquery)
[![License](https://poser.pugx.org/zk2/human-to-tsquery/license)](https://packagist.org/packages/zk2/human-to-tsquery)
[![composer.lock](https://poser.pugx.org/zk2/human-to-tsquery/composerlock)](https://packagist.org/packages/zk2/human-to-tsquery)

Human query to ts_query for PostgreSQL full text search (https://www.postgresql.org/docs/12/textsearch.html). Provides a clement parser and associated tools for a convert human query to query which can be used ih Postgres Full Text Search. Lenient in that is will produce a parse tree for any input, given a default operator and by generally ignoring any unparsable syntax.

The query language supports the following features at a high level:

   - Boolean operators: AND (infix), OR (infix), "-" (prefix) with an implied default operator and precedence rules, e.g. "boy OR girl -infant"

   - Proximity Near Operator (Nx) - `television N2 violence` - Finds words within x number of words from each other, regardless of the order in which they occur.

   - Proximity Within Operator (Wx)	- `Franklin W2 Roosevelt` - Finds words within x number of words from each other, in the order they are entered in the search.

   - Optional parenthesis for explicitly denoting precedence.

   - Quoted phrases (for proximity matching)

Documentation
-------------

    // Some function, which getting SQL query and return single string result
    $closure = function (string $sql) use ($connection) {
        return $connection->fetchOne($sql);
    };
    $humanToTsQuery = new HumanToTsQuery('Opel AND (auto car (patrol OR diesel OR "electric car") AND -sale)');
    $tsQuery = $humanToTsQuery->getQuery($closure);
    var_dump($tsQuery); // "opel & (auto & car & (patrol | diesel | (electr <-> car)) & !sale)"

    $humanToTsQuery = new HumanToTsQuery('indigenous N2 ("debt financing" OR lalala) AND ("New York" OR Boston)');
    $tsQuery = $humanToTsQuery->getQuery($closure);
    var_dump($tsQuery); // "(indigen <2> ((debt <-> financ) | lalala ) | ((debt <-> financ) | lalala ) <2> indigen | indigen <1> ((debt <-> financ) | lalala ) | ((debt <-> financ) | lalala ) <1> indigen) & ((new <-> york) | boston )"

    $humanToTsQuery = new HumanToTsQuery('(indigenous OR texas) W2 ("debt financing" OR lalala) AND ("New York" OR Boston)');
    $tsQuery = $humanToTsQuery->getQuery($closure);
    var_dump($tsQuery); // "((indigen | texa ) <2> ((debt <-> financ) | lalala ) | (indigen | texa ) <1> ((debt <-> financ) | lalala )) & ((new <-> york) | boston )"

Running the Tests
-----------------

Install the [Composer](http://getcomposer.org/) `dev` dependencies:

    composer install --dev

Then, run the test suite using
[PHPUnit](https://github.com/sebastianbergmann/phpunit/):

    vendor/bin/phpunit

License
-------

This bundle is released under the MIT license. See the complete license in the bundle:

    LICENSE
    
