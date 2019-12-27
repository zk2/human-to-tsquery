Usage
=====

**How to get to_tsquery**

.. code-block:: php

    // Some function, which getting SQL query and return single string result
    $closure = function (string $sql) use ($connection) {
        $stmt = $connection->executeQuery($sql);

        return $stmt->fetchColumn(0);
    };
    $humanToTsQuery = new HumanToTsQuery('Opel AND (auto car (patrol OR diesel OR "electric car") AND -sale)');
    $tsQuery = $humanToTsQuery->getQuery($closure);
    var_dump($tsQuery); // string(66) "opel & (auto & car & (patrol | diesel | (electr <-> car)) & !sale)"
