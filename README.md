zk2\human-to-tsquery
================

[![Build Status](https://travis-ci.org/zk2/human-to-tsquery.svg?branch=master)](https://travis-ci.org/zk2/SPSComponent)

[![Latest Stable Version](https://poser.pugx.org/zk2/human-to-tsquery/v/stable)](https://packagist.org/packages/zk2/sps-component)
[![Total Downloads](https://poser.pugx.org/zk2/human-to-tsquery/downloads)](https://packagist.org/packages/zk2/sps-component)
[![Latest Unstable Version](https://poser.pugx.org/zk2/human-to-tsquery/v/unstable)](https://packagist.org/packages/zk2/sps-component)
[![License](https://poser.pugx.org/zk2/human-to-tsquery/license)](https://packagist.org/packages/zk2/sps-component)
[![composer.lock](https://poser.pugx.org/zk2/human-to-tsquery/composerlock)](https://packagist.org/packages/zk2/sps-component)

Human query to ts_query for PostgreSQL full text search (https://www.postgresql.org/docs/12/textsearch.html). Provides a clement parser and associated tools for a convert human query to query which can be used ih Postgres Full Text Search. Lenient in that is will produce a parse tree for any input, given a default operator and by generally ignoring any unparsable syntax.

The query language supports the following features at a high level:

   - Boolean operators: AND (infix), OR (infix), "-" (prefix) with an implied default operator and precedence rules, e.g. "boy OR girl -infant"

   - Optional parenthesis for explicitly denoting precedence.

   - Quoted phrases (for proximity matching)

Documentation
-------------

[Usage](https://github.com/zk2/human-to-tsquery/blob/master/doc/usage.rst)

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
    
